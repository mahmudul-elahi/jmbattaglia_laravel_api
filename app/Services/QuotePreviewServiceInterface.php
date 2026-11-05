<?php

namespace App\Services;

use App\Services\QuoteService;
use Illuminate\Container\Attributes\Bind;

#[Bind(QuoteService::class)]
interface QuotePreviewServiceInterface
{
    public function put(array $preview): string;

    public function find(string $token): ?array;
}
