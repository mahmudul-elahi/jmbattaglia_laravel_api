<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Request successful', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'statusCode' => $status, 'message' => $message, 'data' => $data], $status);
    }
}
