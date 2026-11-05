<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileRequest;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function __construct(private ProfileService $service) {}

    public function show(ProfileRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->read($request->user()));
    }

    public function update(ProfileRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->update($request->user(), $request->validated()));
    }

    public function password(ProfileRequest $request): JsonResponse
    {
        abort_unless($request->filled('newPassword'), 400, 'New password is required.');

        return $this->update($request);
    }
}
