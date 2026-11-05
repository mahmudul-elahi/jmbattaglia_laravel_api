<?php

namespace App\Contracts\Repositories;

interface QuotePreviewRepositoryInterface
{
    public function put(array $preview): string;

    public function find(string $token): ?array;
}
