<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRequest;
use App\Http\Requests\LookupRequest;
use App\Http\Requests\OrderRequest;
use App\Http\Requests\QuoteRequest;
use App\Http\Requests\RecommendationRequest;
use App\Http\Resources\SubmissionReceiptResource;
use App\Services\AvailabilityService;
use App\Services\ContentService;
use App\Services\LookupService;
use App\Services\OrderingService;
use App\Services\QuoteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderingController extends Controller
{
    public function __construct(private QuoteService $quotes, private OrderingService $orders, private AvailabilityService $availability, private LookupService $lookup) {}

    public function quote(QuoteRequest $request): JsonResponse
    {
        return ApiResponse::success($this->quotes->create($request->validated()));
    }

    public function availability(AvailabilityRequest $request, ?string $quoteId = null): JsonResponse
    {
        $token = $quoteId ?? $request->validated('quoteId');
        abort_unless($token, 400, 'A quoteId is required.');
        $quote = $this->quotes->find($token);

        return ApiResponse::success(['quoteId' => $token, 'appointments' => $this->availability->starts($quote, $request->validated('date'), $request->integer('days', 7)), 'timezone' => config('isn.timezone')]);
    }

    public function submit(OrderRequest $request): JsonResponse
    {
        $key = $request->header('Idempotency-Key', '');
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $key), 400, 'Idempotency-Key must be a random 64-character hexadecimal secret.');

        return ApiResponse::success((new SubmissionReceiptResource($this->orders->submit($key, $request->validated())))->resolve($request), 'Request processed', 202);
    }

    public function receipt(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success((new SubmissionReceiptResource($this->orders->receipt($id, $request->header('Idempotency-Key', ''))))->resolve($request));
    }

    public function email(LookupRequest $request, string $email): JsonResponse
    {
        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 400, 'Invalid email.');

        return ApiResponse::success($this->lookup->exists(mb_strtolower(trim($email))));
    }

    public function start(LookupRequest $request): JsonResponse
    {
        return ApiResponse::success($this->lookup->start($request->validated()));
    }

    public function verify(LookupRequest $request): JsonResponse
    {
        return ApiResponse::success($this->lookup->verify($request->validated('challengeId'), $request->validated('code')));
    }

    public function client(LookupRequest $request): JsonResponse
    {
        return ApiResponse::success($this->lookup->createClient($request->validated()));
    }

    public function recommendations(RecommendationRequest $request, ContentService $content): JsonResponse
    {
        return ApiResponse::success($content->recommend($request->validated()));
    }

    public function retired(): JsonResponse
    {
        abort(410, 'Use POST /api/v1/portal/order.');
    }
}
