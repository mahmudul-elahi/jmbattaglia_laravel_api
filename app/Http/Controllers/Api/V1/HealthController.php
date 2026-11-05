<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return ApiResponse::success(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $key = 'health:'.bin2hex(random_bytes(8));
            $store = Cache::store(config('portal.preview_store'));
            $store->put($key, true, 10);
            $ok = $store->pull($key) === true;
            abort_unless($ok, 503);
        } catch (\Throwable) {
            abort(503, 'Service temporarily unavailable.');
        }

        return ApiResponse::success(['status' => 'ready']);
    }
}
