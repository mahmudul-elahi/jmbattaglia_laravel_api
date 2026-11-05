<?php

namespace App\Support;

final class Money
{
    public static function cents(mixed $amount): int
    {
        if (! is_int($amount) && ! is_float($amount) && ! is_string($amount)) {
            throw new \InvalidArgumentException('Invalid monetary amount.');
        }
        $text = (string) $amount;
        if (! preg_match('/^(-?)([0-9]{1,7})(?:\.([0-9]{1,8}))?$/D', $text, $matches)) {
            throw new \InvalidArgumentException('Invalid monetary amount.');
        }
        $whole = (int) $matches[2];
        $fraction = str_pad($matches[3] ?? '', 3, '0');
        $cents = $whole * 100 + (int) substr($fraction, 0, 2) + ((int) $fraction[2] >= 5 ? 1 : 0);
        if ($cents > 100000000) {
            throw new \InvalidArgumentException('Monetary amount exceeds the limit.');
        }

        return $matches[1] === '-' ? -$cents : $cents;
    }
}
