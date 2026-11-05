<?php

namespace App\Services;

interface QuotePreviewServiceInterface
{
    public function put(array $preview): string;

    public function find(string $token): ?array;
}
