<?php

namespace App\Services;

use App\Services\IsnGateway;
use Illuminate\Container\Attributes\Bind;

#[Bind(IsnGateway::class)]
interface IsnGatewayInterface
{
    public function configured(): bool;

    public function catalog(): array;

    public function capabilities(): array;

    public function package(string $id): array;

    public function writePackage(?string $id, array $input, bool $delete = false): array;

    public function quote(array $property, array $services, ?string $packageId, string $orderTypeId): array;

    public function availability(string $zip, array $services, int $offset, int $days, ?string $inspectorId = null): array;

    public function lookup(string $email, string $kind = 'client'): ?array;

    public function ensureContact(array $contact, string $kind = 'client'): array;

    public function createOrder(array $payload): array;

    public function readOrder(string $id): array;
}
