<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentRequest;
use App\Http\Resources\PortalResource;
use App\Models\ContentEntry;
use App\Services\ContentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ContentController extends Controller
{
    public function __construct(private ContentService $service) {}

    public function index(ContentRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->listing('content', $request->validated(), $request->is('api/v1/portal/*')));
    }

    public function show(ContentRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success((new PortalResource($this->service->find('content', $id, $request->is('api/v1/portal/*'))))->resolve($request));
    }

    public function store(ContentRequest $request): JsonResponse
    {
        Gate::authorize('create', ContentEntry::class);

        return ApiResponse::success((new PortalResource($this->service->save('content', null, $request->validated(), $request->user()->id)))->resolve($request), 'Content created', 201);
    }

    public function update(ContentRequest $request, int $id): JsonResponse
    {
        Gate::authorize('update', $this->service->find('content', $id));

        return ApiResponse::success((new PortalResource($this->service->save('content', $id, $request->validated(), $request->user()->id)))->resolve($request));
    }

    private function transition(ContentRequest $request, int $id, string $status): JsonResponse
    {
        Gate::authorize('publish', $this->service->find('content', $id));

        return ApiResponse::success((new PortalResource($this->service->transition('content', $id, $request->integer('version'), $status, $request->user()->id)))->resolve($request));
    }

    public function publish(ContentRequest $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'PUBLISHED');
    }

    public function draft(ContentRequest $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'DRAFT');
    }

    public function archive(ContentRequest $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'ARCHIVED');
    }
}
