<?php

namespace App\Services;

use App\Models\OrderSubmission;

final class SubmissionRecoveryService
{
    public function __construct(private OrderingService $ordering) {}

    public function listing(array $filters): array
    {
        return $this->ordering->listing($filters);
    }

    public function inspect(int $id): OrderSubmission
    {
        return $this->ordering->inspect($id);
    }
}
