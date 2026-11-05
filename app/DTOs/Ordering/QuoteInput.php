<?php

namespace App\DTOs\Ordering;

final readonly class QuoteInput
{
    /**
     * @param  array{address1:string, address2?:?string, city:string, state:string, zip:string, yearBuilt:int, squareFeet:int, foundationType:string, clientAttending:bool, occupied:bool, gatedCommunity:bool, alarmSystem:bool, poolSpa:bool, sewageSystem:string, waterSystem:string, utilitiesOn:bool, referral:string, notes?:?string, ...}  $property
     * @param  list<int|string>  $serviceIds  Integer local add-on IDs or string provider service IDs.
     */
    public function __construct(public string $category, public array $property, public array $serviceIds = [], public ?string $packageId = null, public ?string $orderTypeId = null) {}

    public static function fromArray(array $input): self
    {
        return new self($input['category'], $input['property'], $input['serviceIds'] ?? [], $input['packageId'] ?? null, $input['orderTypeId'] ?? null);
    }

    public function toArray(): array
    {
        return array_filter(['category' => $this->category, 'property' => $this->property, 'serviceIds' => $this->serviceIds, 'packageId' => $this->packageId, 'orderTypeId' => $this->orderTypeId], fn ($value) => $value !== null);
    }
}
