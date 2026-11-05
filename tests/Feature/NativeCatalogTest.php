<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);
it('uses provider string package IDs for writes and invalidates native catalog cache', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('ADMIN');
    $this->withToken($user->createToken('test')->plainTextToken);
    config(['isn.mode' => 'isn', 'isn.base_url' => 'https://packages.test', 'isn.access_key' => 'fixture', 'isn.secret_key' => 'secret', 'isn.writes_enabled' => true]);
    Http::preventStrayRequests();
    $state = (object) ['name' => 'Original'];
    Http::fake(function ($request) use ($state) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if ($path === '/package/provider-package' && $request->method() === 'POST') {
            $state->name = $request['name'];

            return Http::response(['status' => 'ok', 'id' => 'provider-package']);
        }
        $row = ['id' => 'provider-package', 'name' => $state->name, 'active' => 'Yes', 'show' => 'Yes', 'services' => []];

        return Http::response(['status' => 'ok'] + match ($path) {
            '/services' => ['services' => []],'/packages' => ['packages' => [$row]],'/ordertypes' => ['ordertypes' => []],'/foundationtypes' => ['foundationtypes' => []],'/package/provider-package' => ['package' => $row],default => []
        });
    });
    $this->getJson('/api/v1/admin/packages')->assertOk()->assertJsonPath('data.items.0.name', 'Original');
    $this->patchJson('/api/v1/admin/packages/provider-package', ['name' => 'Updated', 'services' => []])->assertOk()->assertJsonPath('data.id', 'provider-package');
    $this->getJson('/api/v1/portal/packages')->assertOk()->assertJsonPath('data.0.name', 'Updated');
    $this->assertDatabaseCount('catalog_items', 0);
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['services'] === []);
});
it('blocks native package mutations when the write switch is disabled', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('ADMIN');
    $this->withToken($user->createToken('test')->plainTextToken);
    config(['isn.mode' => 'isn', 'isn.base_url' => 'https://packages.test', 'isn.access_key' => 'fixture', 'isn.secret_key' => 'secret', 'isn.writes_enabled' => false]);
    Http::preventStrayRequests();
    $this->postJson('/api/v1/admin/packages', ['name' => 'Forbidden'])->assertStatus(503);
    Http::assertNothingSent();
});
