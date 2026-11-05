<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\NativeCatalogController;
use App\Http\Controllers\Api\V1\OrderingController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function (): void {
    // Public auth endpoints
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('forgot-password', [AuthController::class, 'forgot']);
    Route::post('verify-reset-otp', [AuthController::class, 'verifyReset']);
    Route::post('reset-password', [AuthController::class, 'reset']);
    Route::post('email-verification/send', [AuthController::class, 'sendEmail']);
    Route::post('email-verification/verify', [AuthController::class, 'verifyEmail']);

    // Authenticated session endpoints
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Public Portal Routes (Booking & Catalog)
|--------------------------------------------------------------------------
*/
Route::prefix('portal')->group(function (): void {
    // Content & Settings
    Route::get('content', [ContentController::class, 'index']);
    Route::get('content/{id}', [ContentController::class, 'show'])->whereNumber('id');
    Route::get('settings', [SettingsController::class, 'show']);

    // Addons & Catalog
    Route::get('addons', [CatalogController::class, 'index']);
    Route::get('addons/{id}', [CatalogController::class, 'show'])->whereNumber('id');
    Route::get('catalog', [NativeCatalogController::class, 'catalog']);
    Route::get('catalog/{id}', [NativeCatalogController::class, 'entry']);
    Route::get('services', [NativeCatalogController::class, 'services']);
    Route::get('packages', [NativeCatalogController::class, 'index']);
    Route::get('packages/{id}', [NativeCatalogController::class, 'show']);
    Route::get('ordertypes', [NativeCatalogController::class, 'orderTypes']);
    Route::get('foundationtypes', [NativeCatalogController::class, 'foundations']);
    Route::get('booking-options', [NativeCatalogController::class, 'options']);

    // Client lookup & verification
    Route::get('client/lookup/{email}', [OrderingController::class, 'email']);
    Route::post('lookup/start', [OrderingController::class, 'start']);
    Route::post('lookup/verify', [OrderingController::class, 'verify']);

    // Pricing Quotes & Availability
    Route::post('quotes', [OrderingController::class, 'quote']);
    Route::post('calculateservicequote', [OrderingController::class, 'quote']);
    Route::get('availableslots', [OrderingController::class, 'availability']);
    Route::get('quotes/{quoteId}/availability', [OrderingController::class, 'availability'])->where('quoteId', '[a-f0-9]{64}');

    // Ordering & Submissions
    Route::post('order', [OrderingController::class, 'submit']);
    Route::get('order/{id}', [OrderingController::class, 'receipt'])->whereNumber('id');
    Route::post('recommendations', [OrderingController::class, 'recommendations']);
    Route::post('submissions', [OrderingController::class, 'retired']);

    // Staff client creation
    Route::post('client', [OrderingController::class, 'client'])
        ->middleware(['auth:sanctum', 'role:ADMIN', 'can:clients.manage']);
});

/*
|--------------------------------------------------------------------------
| Protected Admin Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'role:ADMIN'])->group(function (): void {

    // Dashboard
    Route::middleware('can:dashboard.view')->group(function (): void {
        Route::get('dashboard', [AdminController::class, 'dashboard']);
        Route::get('dashboard/metrics', [AdminController::class, 'metrics']);
        Route::get('dashboard/recent-packages', [AdminController::class, 'recent']);
    });

    // Profile Management
    Route::middleware('can:profile.manage')->group(function (): void {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::patch('profile', [ProfileController::class, 'update']);
        Route::post('profile/password', [ProfileController::class, 'password']);
    });

    // Portal Settings
    Route::middleware('can:settings.manage')->group(function (): void {
        Route::get('settings', [SettingsController::class, 'show']);
        Route::patch('settings', [SettingsController::class, 'update']);
    });

    // Content & Recommendations
    Route::middleware('can:content.manage')->group(function (): void {
        Route::get('content', [ContentController::class, 'index']);
        Route::post('content', [ContentController::class, 'store']);
        Route::get('content/{id}', [ContentController::class, 'show'])->whereNumber('id');
        Route::patch('content/{id}', [ContentController::class, 'update'])->whereNumber('id');
        Route::post('content/{id}/publish', [ContentController::class, 'publish'])->whereNumber('id');
        Route::post('content/{id}/draft', [ContentController::class, 'draft'])->whereNumber('id');
        Route::post('content/{id}/archive', [ContentController::class, 'archive'])->whereNumber('id');

        Route::get('recommendations', [AdminController::class, 'rules']);
        Route::post('recommendations', [AdminController::class, 'createRule']);
        Route::delete('recommendations/{id}', [AdminController::class, 'deleteRule'])->whereNumber('id');
    });

    // Catalog & Packages Management
    Route::middleware('can:catalog.manage')->group(function (): void {
        Route::get('catalog', [NativeCatalogController::class, 'catalog']);
        Route::get('catalog/{id}', [NativeCatalogController::class, 'entry']);
        Route::get('services', [NativeCatalogController::class, 'services']);

        Route::apiResource('packages', NativeCatalogController::class)
            ->parameters(['packages' => 'id'])
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::apiResource('addons', CatalogController::class)
            ->parameters(['addons' => 'id'])
            ->whereNumber('id');
    });

    // Media Management
    Route::middleware('can:media.manage')->group(function (): void {
        Route::get('media', [MediaController::class, 'index']);
        Route::post('media/upload', [MediaController::class, 'upload']);
        Route::post('media/uploads', [MediaController::class, 'signed']);
        Route::post('media/{id}/complete', [MediaController::class, 'complete'])->whereNumber('id');
        Route::delete('media/{id}', [MediaController::class, 'destroy'])->whereNumber('id');
    });

    // ISN Integration
    Route::middleware('can:integrations.manage')->group(function (): void {
        Route::get('integrations/isn/status', [NativeCatalogController::class, 'status']);
        Route::get('integrations/isn/catalog', [NativeCatalogController::class, 'catalog']);
    });

    // Submissions & Orders
    Route::middleware('can:submissions.view')->group(function (): void {
        Route::get('submissions', [AdminController::class, 'submissions']);
        Route::get('submissions/{id}', [AdminController::class, 'submission'])->whereNumber('id');
        Route::get('completed-bookings', [AdminController::class, 'completions']);
    });

    Route::middleware('can:submissions.reconcile')->group(function (): void {
        Route::post('submissions/{id}/reconcile', [AdminController::class, 'reconcile'])->whereNumber('id');
        Route::post('completed-bookings/{bookingId}/sync-isn', [AdminController::class, 'sync'])->whereNumber('bookingId');
    });

    // Roles & Permissions
    Route::middleware('can:roles.manage')->group(function (): void {
        Route::get('permissions', [RoleController::class, 'permissions']);
        Route::get('roles', [RoleController::class, 'index']);
        Route::post('roles/assign', [RoleController::class, 'assign']);
        Route::patch('roles/{id}/permissions', [RoleController::class, 'grants'])->whereNumber('id');
    });
});
