<?php

use App\Models\CatalogItem;
use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Models\OrderSubmission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);
it('preserves dashboard summary and recent row metadata in local mode', function (): void {
    config(['isn.mode' => 'local']);
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('ADMIN');
    $this->withToken($user->createToken('test')->plainTextToken);
    CatalogItem::factory()->published()->create(['kind' => 'PACKAGE', 'name' => 'Home Bundle', 'type' => 'BUNDLE', 'price_cents' => 45000]);
    CatalogItem::factory()->create();
    $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonPath('data.summary.totalPackages', 1)->assertJsonPath('data.summary.addOnServices', 1)->assertJsonPath('data.recentPackages.0.price', '$450')->assertJsonPath('data.pagination.showingText', 'Showing 1-1 of 1 items');
    $this->getJson('/api/v1/admin/dashboard/metrics')->assertOk()->assertJsonMissingPath('data.recentPackages');
    $this->getJson('/api/v1/admin/dashboard/recent-packages')->assertOk()->assertJsonPath('data.items.0.name', 'Home Bundle')->assertJsonPath('data.pagination.limit', 5);
});
it('allows name only profile saves with blank credential fields without revoking the bearer token', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('ADMIN');
    $this->withToken($user->createToken('test')->plainTextToken);
    $this->patchJson('/api/v1/admin/profile', ['name' => 'Dana Smith', 'email' => '   ', 'newPassword' => '', 'confirmPassword' => '', 'currentPassword' => ''])->assertOk()->assertJsonPath('data.name', 'Dana Smith')->assertJsonPath('data.requiresReauthentication', false);
    expect($user->tokens()->count())->toBe(1);
});
it('projects safe public media fields and hides internal catalog mappings', function (): void {
    $content = ContentEntry::factory()->published()->create();
    $media = MediaAsset::factory()->create();
    $content->media()->attach($media->id);
    $this->getJson('/api/v1/portal/content/'.$content->id)->assertOk()->assertJsonPath('data.media.0.mimeType', 'image/png')->assertJsonMissingPath('data.media.0.checksum')->assertJsonMissingPath('data.media.0.createdBy')->assertJsonMissingPath('data.version');
    $addon = CatalogItem::factory()->published()->create(['isn_service_ids' => ['private-mapping']]);
    $this->getJson('/api/v1/portal/addons/'.$addon->id)->assertOk()->assertJsonMissingPath('data.isnServiceIds');
});
it('surfaces stalled submissions for review without making external requests', function (): void {
    Http::preventStrayRequests();
    $order = OrderSubmission::factory()->create(['status' => 'PROCESSING', 'phase' => 'ORDER_WRITE_STARTED', 'updated_at' => now()->subMinutes(20)]);
    $this->artisan('portal:recover-stalled')->assertSuccessful();
    expect($order->fresh()->status->value)->toBe('REQUIRES_REVIEW');
    Http::assertNothingSent();
});
it('covers every registered API operation in the generated contract', function (): void {
    $this->artisan('portal:openapi')->assertSuccessful();
    $contract = json_decode(file_get_contents(public_path('openapi.json')), true, 512, JSON_THROW_ON_ERROR);
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/v1/')) {
            continue;
        } foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            expect(isset($contract['paths']['/'.$route->uri()][strtolower($method)]))->toBeTrue();
        }
    }
    expect(isset($contract['paths']['/api/v1/auth/refresh/token']))->toBeFalse();
    $this->getJson('/api/v1/does-not-exist')->assertNotFound()->assertJsonStructure(['timestamp']);
    $this->get('/api-explorer')->assertOk()->assertSee('At Ease API');
});
