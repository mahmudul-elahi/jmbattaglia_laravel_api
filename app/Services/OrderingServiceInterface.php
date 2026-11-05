<?php

namespace App\Services;

use App\Models\OrderSubmission;

interface OrderingServiceInterface
{
    public function listing(array $filters): array;

    public function inspect(int $id): OrderSubmission;

    public function findByKey(string $key): ?OrderSubmission;

    public function reserve(string $key, string $hash, array $input, array $preview): OrderSubmission;

    public function startWrite(int $id): bool;

    public function claim(int $id): bool;

    public function update(int $id, array $attributes): OrderSubmission;

    public function complete(int $id, ?int $actor = null): OrderSubmission;

    public function receipt(int $id, string $key): OrderSubmission;
}
