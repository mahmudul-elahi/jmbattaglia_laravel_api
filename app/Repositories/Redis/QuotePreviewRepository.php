<?php

namespace App\Repositories\Redis;

use App\Contracts\Repositories\QuotePreviewRepositoryInterface;
use Illuminate\Support\Facades\Cache;

final class QuotePreviewRepository implements QuotePreviewRepositoryInterface
{
    public function put(array $preview): string
    {
        $token = bin2hex(random_bytes(32));
        Cache::store(config('portal.preview_store'))->put('portal:quote:'.$token, $preview, 1800);

        return $token;
    }

    public function find(string $token): ?array
    {
        return Cache::store(config('portal.preview_store'))->get('portal:quote:'.$token);
    }
}
