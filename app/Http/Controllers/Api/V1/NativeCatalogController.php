<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PackageRequest;
use App\Services\NativeCatalogService;
use App\Support\ApiResponse;
use App\Support\PropertyOptions;
use Illuminate\Http\JsonResponse;

class NativeCatalogController extends Controller
{
    public function __construct(private NativeCatalogService $service) {}

    public function catalog(PackageRequest $request): JsonResponse
    {
        return ApiResponse::success($request->is('api/v1/admin/integrations/*') ? $this->service->catalog(false) : $this->service->list($request->validated(), $request->is('api/v1/portal/*')));
    }

    public function services(PackageRequest $request): JsonResponse
    {
        return ApiResponse::success($request->is('api/v1/portal/*') ? $this->service->catalog()['services'] : $this->service->list($request->validated(), false, 'SERVICE'));
    }

    public function index(PackageRequest $request): JsonResponse
    {
        return ApiResponse::success($request->is('api/v1/portal/*') ? $this->service->catalog()['packages'] : $this->service->list($request->validated(), false, in_array($request->input('tab'), ['add-ons', 'addons'], true) ? 'ADDON' : 'PACKAGE'));
    }

    public function entry(PackageRequest $request, string $id): JsonResponse
    {
        return ApiResponse::success($this->service->entry($id, $request->is('api/v1/portal/*')));
    }

    public function show(PackageRequest $request, string $id): JsonResponse
    {
        return ApiResponse::success($this->service->package($id, $request->is('api/v1/portal/*')));
    }

    public function store(PackageRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->gateway->writePackage(null, $request->validated()), 'Package created', 201);
    }

    public function update(PackageRequest $request, string $id): JsonResponse
    {
        return ApiResponse::success($this->service->gateway->writePackage($id, $request->validated()));
    }

    public function destroy(PackageRequest $request, string $id): JsonResponse
    {
        return ApiResponse::success($this->service->gateway->writePackage($id, [], true));
    }

    public function orderTypes(PackageRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->catalog()['orderTypes']);
    }

    public function foundations(PackageRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->catalog()['foundationTypes']);
    }

    public function options(PackageRequest $request): JsonResponse
    {
        $catalog = $this->service->catalog();

        return ApiResponse::success(['orderTypes' => $catalog['orderTypes'], 'foundationTypes' => $catalog['foundationTypes'], 'propertyChoices' => PropertyOptions::CHOICES, 'foundationAliases' => PropertyOptions::FOUNDATION_ALIASES, 'timezone' => config('isn.timezone')]);
    }

    public function status(PackageRequest $request): JsonResponse
    {
        $configured = $this->service->gateway->configured();
        $data = ['configured' => $configured, 'connected' => false, 'canQuote' => false, 'canCreateAgent' => false, 'writesEnabled' => false];
        if ($configured) {
            try {
                $data = array_merge($data, ['connected' => true], $this->service->gateway->capabilities());
            } catch (\Throwable) {
            }
        }

        return ApiResponse::success($data);
    }
}
