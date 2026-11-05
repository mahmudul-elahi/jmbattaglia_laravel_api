<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\NativeCatalogController;
use App\Http\Controllers\Api\V1\OrderingController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::group([], function (): void {
        foreach (['login' => 'login', 'register' => 'register', 'forgot-password' => 'forgot', 'verify-reset-otp' => 'verifyReset', 'reset-password' => 'reset', 'email-verification/send' => 'sendEmail', 'email-verification/verify' => 'verifyEmail'] as $path => $action) {
            Route::post($path, [AuthController::class, $action]);
        }
    });
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});
Route::get('health/live', [HealthController::class, 'live']);
Route::get('health/ready', [HealthController::class, 'ready']);
Route::prefix('portal')->group(function (): void {
    Route::get('content', [ContentController::class, 'index']);
    Route::get('content/{id}', [ContentController::class, 'show'])->whereNumber('id');
    Route::get('addons', [CatalogController::class, 'index']);
    Route::get('addons/{id}', [CatalogController::class, 'show'])->whereNumber('id');
    Route::get('settings', [SettingsController::class, 'show']);
    foreach (['catalog' => 'catalog', 'services' => 'services', 'packages' => 'index', 'ordertypes' => 'orderTypes', 'foundationtypes' => 'foundations', 'booking-options' => 'options'] as $path => $action) {
        Route::get($path, [NativeCatalogController::class, $action]);
    }
    Route::get('packages/{id}', [NativeCatalogController::class, 'show']);
    Route::get('catalog/{id}', [NativeCatalogController::class, 'entry']);
    Route::get('client/lookup/{email}', [OrderingController::class, 'email']);
    Route::post('lookup/start', [OrderingController::class, 'start']);
    Route::post('lookup/verify', [OrderingController::class, 'verify']);
    foreach (['quotes', 'calculateservicequote'] as $path) {
        Route::post($path, [OrderingController::class, 'quote']);
    }
    Route::get('availableslots', [OrderingController::class, 'availability']);
    Route::get('quotes/{quoteId}/availability', [OrderingController::class, 'availability'])->where('quoteId', '[a-f0-9]{64}');
    Route::post('order', [OrderingController::class, 'submit']);
    Route::get('order/{id}', [OrderingController::class, 'receipt'])->whereNumber('id');
    Route::post('recommendations', [OrderingController::class, 'recommendations']);
    Route::post('submissions', [OrderingController::class, 'retired']);
    Route::post('client', [OrderingController::class, 'client'])->middleware(['auth:sanctum', 'role:ADMIN', 'can:clients.manage']);
});
Route::prefix('admin')->middleware(['auth:sanctum', 'role:ADMIN'])->group(function (): void {
    foreach (['dashboard' => 'dashboard', 'dashboard/metrics' => 'metrics', 'dashboard/recent-packages' => 'recent'] as $path => $action) {
        Route::get($path, [AdminController::class, $action])->middleware('can:dashboard.view');
    }
    Route::middleware('can:profile.manage')->group(function (): void {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::patch('profile', [ProfileController::class, 'update']);
        Route::post('profile/password', [ProfileController::class, 'password']);
    });
    Route::middleware('can:settings.manage')->group(function (): void {
        Route::get('settings', [SettingsController::class, 'show']);
        Route::patch('settings', [SettingsController::class, 'update']);
    });
    Route::middleware('can:content.manage')->group(function (): void {
        Route::get('content', [ContentController::class, 'index']);
        Route::post('content', [ContentController::class, 'store']);
        Route::get('content/{id}', [ContentController::class, 'show'])->whereNumber('id');
        Route::patch('content/{id}', [ContentController::class, 'update'])->whereNumber('id');
        foreach (['publish', 'draft', 'archive'] as $action) {
            Route::post('content/{id}/'.$action, [ContentController::class, $action])->whereNumber('id');
        }
        Route::get('recommendations', [AdminController::class, 'rules']);
        Route::post('recommendations', [AdminController::class, 'createRule']);
        Route::delete('recommendations/{id}', [AdminController::class, 'deleteRule'])->whereNumber('id');
    });
    Route::middleware('can:catalog.manage')->group(function (): void {
        Route::get('catalog', [NativeCatalogController::class, 'catalog']);
        Route::get('catalog/{id}', [NativeCatalogController::class, 'entry']);
        Route::get('services', [NativeCatalogController::class, 'services']);
        Route::apiResource('packages', NativeCatalogController::class)->parameters(['packages' => 'id'])->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::apiResource('addons', CatalogController::class)->parameters(['addons' => 'id'])->whereNumber('id');
    });
    Route::middleware('can:media.manage')->group(function (): void {
        Route::get('media', [MediaController::class, 'index']);
        Route::post('media/upload', [MediaController::class, 'upload']);
        Route::post('media/uploads', [MediaController::class, 'signed']);
        Route::post('media/{id}/complete', [MediaController::class, 'complete'])->whereNumber('id');
        Route::delete('media/{id}', [MediaController::class, 'destroy'])->whereNumber('id');
    });
    Route::get('integrations/isn/status', [NativeCatalogController::class, 'status'])->middleware('can:integrations.manage');
    Route::get('integrations/isn/catalog', [NativeCatalogController::class, 'catalog'])->middleware('can:integrations.manage');
    Route::get('submissions', [AdminController::class, 'submissions'])->middleware('can:submissions.view');
    Route::get('submissions/{id}', [AdminController::class, 'submission'])->whereNumber('id')->middleware('can:submissions.view');
    Route::post('submissions/{id}/reconcile', [AdminController::class, 'reconcile'])->whereNumber('id')->middleware('can:submissions.reconcile');
    Route::get('completed-bookings', [AdminController::class, 'completions'])->middleware('can:submissions.view');
    Route::post('completed-bookings/{bookingId}/sync-isn', [AdminController::class, 'sync'])->whereNumber('bookingId')->middleware('can:submissions.reconcile');
    Route::get('permissions', [RoleController::class, 'permissions'])->middleware('can:roles.manage');
    Route::patch('roles/{id}/permissions', [RoleController::class, 'grants'])->whereNumber('id')->middleware('can:roles.manage');
    Route::get('roles', [RoleController::class, 'index'])->middleware('can:roles.manage');
    Route::post('roles/assign', [RoleController::class, 'assign'])->middleware('can:roles.manage');
});
