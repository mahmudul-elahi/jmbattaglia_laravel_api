<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingsRequest;
use App\Http\Resources\PortalResource;
use App\Services\ContentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct(private ContentService $content) {}

    public function show(SettingsRequest $request): JsonResponse
    {
        return ApiResponse::success((new PortalResource($this->content->settings()))->resolve($request));
    }

    public function update(SettingsRequest $request): JsonResponse
    {
        return ApiResponse::success((new PortalResource($this->content->updateSettings($request->validated(), $request->user()->id)))->resolve($request));
    }
}
