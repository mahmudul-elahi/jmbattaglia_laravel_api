<?php

use App\Models\AuditLog;
use App\Models\ContentEntry;
use App\Models\User;
use Database\Seeders\PortalSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
it('publishes validated content and rejects stale writes without an audit mutation', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('ADMIN');
    $this->withToken($user->createToken('test')->plainTextToken);
    $response = $this->postJson('/api/v1/admin/content', ['key' => 'buying.guide', 'kind' => 'EDUCATION', 'title' => 'Buyer guide', 'body' => 'Prepare for inspection'])->assertCreated()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.version', 1);
    $id = $response->json('data.id');
    expect($id)->toBeInt();
    $this->getJson('/api/v1/portal/content/'.$id)->assertNotFound();
    $this->postJson('/api/v1/admin/content/'.$id.'/publish', ['version' => 1])->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.status', 'PUBLISHED');
    $this->getJson('/api/v1/portal/content/'.$id)->assertOk()->assertJsonPath('data.title', 'Buyer guide');
    $count = AuditLog::count();
    $this->patchJson('/api/v1/admin/content/'.$id, ['version' => 1, 'title' => 'Stale edit'])->assertConflict();
    expect(AuditLog::count())->toBe($count);
    $this->assertDatabaseHas('content_entries', ['id' => $id, 'title' => 'Buyer guide', 'version' => 2]);
});
it('rejects empty publication and preserves versioned singleton settings', function (): void {
    $this->seed([RolePermissionSeeder::class, PortalSettingsSeeder::class]);
    $user = User::factory()->create();
    $user->assignRole('ADMIN');
    $this->withToken($user->createToken('test')->plainTextToken);
    $id = $this->postJson('/api/v1/admin/content', ['key' => 'empty', 'kind' => 'RESOURCE', 'title' => 'Missing URL'])->assertCreated()->json('data.id');
    $this->postJson('/api/v1/admin/content/'.$id.'/publish', ['version' => 1])->assertStatus(400);
    $this->patchJson('/api/v1/admin/settings', ['version' => 1, 'brandName' => 'New Brand'])->assertOk()->assertJsonPath('data.version', 2);
    $this->patchJson('/api/v1/admin/settings', ['version' => 1, 'brandName' => 'Stale'])->assertConflict();
    $this->getJson('/api/v1/portal/settings')->assertOk()->assertJsonPath('data.brandName', 'New Brand');
});
it('returns a safe 409 for duplicate content keys without an extra audit record', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('ADMIN');
    $this->withToken($user->createToken('test')->plainTextToken);
    $input = ['key' => 'unique.guide', 'kind' => 'EDUCATION', 'title' => 'Buyer guide', 'body' => 'Helpful information.'];
    $this->postJson('/api/v1/admin/content', $input)->assertCreated();
    $this->postJson('/api/v1/admin/content', $input)->assertConflict()->assertJsonPath('success', false);
    expect(ContentEntry::count())->toBe(1);
    expect(AuditLog::count())->toBe(1);
});
