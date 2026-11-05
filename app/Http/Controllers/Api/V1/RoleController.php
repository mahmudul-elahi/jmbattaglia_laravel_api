<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Services\RolePermissionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct(private RolePermissionService $service) {}

    public function index(RoleRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->roles());
    }

    public function permissions(RoleRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->permissions());
    }

    public function assign(RoleRequest $request): JsonResponse
    {
        $this->service->assign($request->integer('userId'), $request->validated('role'), $request->user()->id);

        return ApiResponse::success();
    }

    public function grants(RoleRequest $request, int $id): JsonResponse
    {
        $this->service->grants($id, $request->validated('permissions'), $request->user()->id);

        return ApiResponse::success();
    }
}
