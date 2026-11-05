<?php

namespace App\Services;

use App\Contracts\Repositories\OrderingRepositoryInterface;
use App\Models\OrderSubmission;

final class SubmissionRecoveryService
{
    public function __construct(private OrderingRepositoryInterface $repository) {}

    public function listing(array $filters): array
    {
        return $this->repository->listing($filters);
    }

    public function inspect(int $id): OrderSubmission
    {
        return $this->repository->inspect($id);
    }
}
