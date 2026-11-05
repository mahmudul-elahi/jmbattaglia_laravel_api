<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Repositories\AccessTokenRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuthRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\PasswordRecoveryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth, private PasswordRecoveryService $recovery, private AccessTokenRepositoryInterface $tokens) {}

    public function login(AuthRequest $request): JsonResponse
    {
        $data = $this->auth->login($request->validated());
        $data['user'] = (new UserResource($data['user']))->resolve($request);

        return ApiResponse::success($data);
    }

    public function register(AuthRequest $request): JsonResponse
    {
        return ApiResponse::success((new UserResource($this->auth->register($request->validated())))->resolve($request), 'Account created', 201);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success((new UserResource($request->user()->load(['roles.permissions', 'permissions', 'profile'])))->resolve($request));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->tokens->revokeCurrent($request->user());

        return ApiResponse::success(null, 'Logged out');
    }

    public function forgot(AuthRequest $request): JsonResponse
    {
        $this->recovery->send($request->validated('email'), 'PASSWORD_RESET');

        return ApiResponse::success(null, 'If the account exists, a verification code has been sent.');
    }

    public function sendEmail(AuthRequest $request): JsonResponse
    {
        $this->recovery->send($request->validated('email'), 'EMAIL_VERIFICATION');

        return ApiResponse::success(null, 'If eligible, a verification code has been sent.');
    }

    public function verifyReset(AuthRequest $request): JsonResponse
    {
        return ApiResponse::success($this->recovery->verify($request->validated('email'), 'PASSWORD_RESET', $request->validated('otp')));
    }

    public function verifyEmail(AuthRequest $request): JsonResponse
    {
        return ApiResponse::success($this->recovery->verify($request->validated('email'), 'EMAIL_VERIFICATION', $request->validated('otp')));
    }

    public function reset(AuthRequest $request): JsonResponse
    {
        $this->recovery->reset($request->validated('resetToken'), $request->validated('newPassword'));

        return ApiResponse::success(null,'Password reset. Please log in.');
    }
}
