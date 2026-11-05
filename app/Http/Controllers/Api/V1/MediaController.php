<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MediaRequest;
use App\Services\MediaService;
use App\Support\ApiResponse;
use App\Support\PortalSerializer;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    public function __construct(private MediaService $service) {}

    public function index(MediaRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->listing($request->integer('page', 1), $request->integer('limit', 20)));
    }

    public function upload(MediaRequest $request): JsonResponse
    {
        return ApiResponse::success(PortalSerializer::model($this->service->upload($request->file('file'), $request->user()->id, $request->validated('altText', ''))), 'Media uploaded', 201);
    }

    public function signed(MediaRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->signed($request->validated(), $request->user()->id), 'Upload reserved', 201);
    }

    public function complete(MediaRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(PortalSerializer::model($this->service->complete($id)));
    }

    public function destroy(MediaRequest $request, int $id): JsonResponse
    {
        $this->service->delete($id);

        return ApiResponse::success();
    }
}
