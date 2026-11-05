<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogRequest;
use App\Http\Resources\PortalResource;
use App\Services\ContentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function __construct(private ContentService $service) {}

    public function index(CatalogRequest $request): JsonResponse
    {
        $public = $request->is('api/v1/portal/*');
        $data = $this->service->repository->listing('addons', $request->validated(), $public);

        return ApiResponse::success($public ? $data['items'] : $data);
    }

    public function show(CatalogRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success((new PortalResource($this->service->repository->find('addons', $id, $request->is('api/v1/portal/*'))))->resolve($request));
    }

    public function store(CatalogRequest $request): JsonResponse
    {
        return ApiResponse::success((new PortalResource($this->service->save('addons', null, $request->validated(), $request->user()->id)))->resolve($request), 'Add-on created', 201);
    }

    public function update(CatalogRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success((new PortalResource($this->service->save('addons', $id, $request->validated(), $request->user()->id)))->resolve($request));
    }

    public function destroy(CatalogRequest $request, int $id): JsonResponse
    {
        $this->service->repository->deleteAddon($id, $request->validated('version'), $request->boolean('permanent'), $request->user()->id);

        return ApiResponse::success();
    }
}
