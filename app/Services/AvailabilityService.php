<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final class AvailabilityService
{
    public function __construct(private IsnGatewayInterface $gateway) {}

    public function starts(array $quote, string $date, int $days = 7): array
    {
        $timezone = config('isn.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        $offset = (int) $today->diffInDays($day, false);
        abort_if($offset < 0 || $offset > 90 || $days < 1 || $days > 14 || $offset + $days > 91, 400, 'Availability must be within the next 90 days.');
        $duration = $quote['durationMinutes'];
        abort_unless(is_int($duration) && $duration > 0, 503, 'Quote has no valid duration.');
        $windows = $this->gateway->availability($quote['property']['zip'], $quote['serviceIds'], $offset, $days);
        $starts = [];
        foreach ($windows as $window) {
            $start = CarbonImmutable::parse($window['start'], $timezone)->setTimezone($timezone)->max($day);
            $end = CarbonImmutable::parse($window['end'], $timezone)->setTimezone($timezone)->min($day->addDays($days));
            if ($start->second !== 0 || $start->minute % 15 !== 0) {
                $start = $start->startOfMinute()->addMinutes(15 - $start->minute % 15);
            }
            while ($start->addMinutes($duration)->lte($end)) {
                if ($this->isUnambiguous($start) && $start->isFuture() && $start->gte($day) && $start->lt($day->addDays($days))) {
                    $key = $start->format('Y-m-d H:i').'|'.$window['inspectorId'];
                    $starts[$key] = ['date' => $start->format('Y-m-d'), 'time' => $start->format('H:i'), 'inspectorId' => $window['inspectorId'], 'inspectorName' => $window['inspectorName'], 'durationMinutes' => $duration];
                }
                $start = $start->addMinutes(15);
            }
        }
        ksort($starts);

        return array_values($starts);
    }

    public function assertSelected(array $quote, array $input): void
    {
        $starts = $this->starts($quote, $input['date'], 1);
        abort_unless(collect($starts)->contains(fn ($slot) => $slot['date'] === $input['date'] && $slot['time'] === $input['time'] && $slot['inspectorId'] === $input['inspectorId']), 409, 'The selected appointment is unavailable.');
    }

    private function isUnambiguous(CarbonImmutable $time): bool
    {
        $transitions = $time->getTimezone()->getTransitions($time->timestamp - 86400, $time->timestamp + 86400);
        if ($transitions === false) {
            return true;
        }
        $previous = null;
        foreach ($transitions as $transition) {
            if ($previous !== null && $transition['offset'] < $previous) {
                $overlap = $previous - $transition['offset'];
                if ($time->timestamp >= $transition['ts'] - $overlap && $time->timestamp < $transition['ts'] + $overlap) {
                    return false;
                }
            }
            $previous = $transition['offset'];
        }

        return true;
    }
}
