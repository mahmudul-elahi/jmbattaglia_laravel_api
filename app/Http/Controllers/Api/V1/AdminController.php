<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminQueryRequest;
use App\Http\Requests\RecommendationRequest;
use App\Http\Requests\ReconcileRequest;
use App\Services\ContentService;
use App\Services\DashboardService;
use App\Services\HistoricalBookingService;
use App\Services\NativeCatalogService;
use App\Services\OrderingService;
use App\Services\SubmissionRecoveryService;
use App\Support\ApiResponse;
use App\Support\PortalSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class AdminController extends Controller
{
    public function __construct(
        private NativeCatalogService $native,
        private ContentService $content,
        private OrderingService $orders,
        private SubmissionRecoveryService $recovery,
        private HistoricalBookingService $history
    ) {}

    public function dashboard(AdminQueryRequest $request, DashboardService $dashboard): JsonResponse
    {
        return ApiResponse::success($dashboard->data($request->validated()));
    }

    public function metrics(AdminQueryRequest $request, DashboardService $dashboard): JsonResponse
    {
        return ApiResponse::success(Arr::except($dashboard->data($request->validated()), ['recentPackages', 'pagination']));
    }

    public function recent(AdminQueryRequest $request, DashboardService $dashboard): JsonResponse
    {
        $data = $dashboard->data($request->validated());

        return ApiResponse::success(['items' => $data['recentPackages'], 'pagination' => $data['pagination']]);
    }

    public function rules(RecommendationRequest $request): JsonResponse
    {
        return ApiResponse::success($this->content->rules()->map(fn ($rule) => PortalSerializer::model($rule)));
    }

    public function createRule(RecommendationRequest $request): JsonResponse
    {
        return ApiResponse::success(PortalSerializer::model($this->content->createRule($request->validated(), $request->user()->id)), 'Rule created', 201);
    }

    public function deleteRule(RecommendationRequest $request, int $id): JsonResponse
    {
        $this->content->deleteRule($id, $request->user()->id);

        return ApiResponse::success();
    }

    public function submissions(AdminQueryRequest $request): JsonResponse
    {
        return ApiResponse::success($this->recovery->listing($request->validated()));
    }

    public function submission(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success(PortalSerializer::model($this->recovery->inspect($id)));
    }

    public function reconcile(ReconcileRequest $request, int $id): JsonResponse
    {
        Gate::authorize('reconcile', $this->recovery->inspect($id));

        return ApiResponse::success(PortalSerializer::model($this->orders->reconcile($id, $request->validated('remoteOrderId'), $request->user()->id)));
    }

    public function completions(AdminQueryRequest $request): JsonResponse
    {
        return ApiResponse::success($this->history->listing($request->integer('page', 1), $request->integer('limit', 20)));
    }

    public function sync(Request $request, int $bookingId, HistoricalBookingService $history): JsonResponse
    {
        return ApiResponse::success(PortalSerializer::model($history->sync($bookingId)));
    }
}
