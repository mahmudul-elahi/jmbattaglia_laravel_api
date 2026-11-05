<?php

namespace App\DTOs\Ordering;

final readonly class OrderInput
{
    /**
     * @param  array{firstName:string, lastName:string, email:string, phone:string}  $contact
     * @param  array{firstName:string, lastName:string, email:string, phone:string}|null  $buyerContact
     */
    public function __construct(public QuoteInput $selection, public string $quoteId, public string $customerType, public array $contact, public string $date, public string $time, public string $inspectorId, public ?array $buyerContact = null) {}

    public static function fromArray(array $input): self
    {
        return new self(QuoteInput::fromArray($input), $input['quoteId'], $input['customerType'], $input['contact'], $input['date'], $input['time'], $input['inspectorId'], $input['buyerContact'] ?? null);
    }

    public function toArray(): array
    {
        return $this->selection->toArray() + array_filter(['quoteId' => $this->quoteId, 'customerType' => $this->customerType, 'contact' => $this->contact, 'date' => $this->date, 'time' => $this->time, 'inspectorId' => $this->inspectorId, 'buyerContact' => $this->buyerContact], fn ($value) => $value !== null);
    }
}
