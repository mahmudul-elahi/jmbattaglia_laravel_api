<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);
it('issues an expiring hashed bearer token and revokes it on logout', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['email' => 'admin@example.com', 'password' => 'Correct password 2026!']);
    $user->assignRole('ADMIN');
    $response = $this->postJson('/api/v1/auth/login', ['email' => ' ADMIN@EXAMPLE.COM ', 'password' => 'Correct password 2026!'])->assertOk()->assertJsonPath('data.user.id', $user->id)->assertJsonPath('data.user.role', 'ADMIN')->assertJsonPath('data.expiresIn', 28800);
    $token = $response->json('data.accessToken');
    expect(PersonalAccessToken::findToken($token))->not->toBeNull();
    expect(PersonalAccessToken::first()->token)->not->toBe($token);
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    expect(PersonalAccessToken::findToken($token))->toBeNull();
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});
it('rejects unknown login fields and wrong credentials with the compatibility envelope', function (): void {
    $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'invalid', 'rememberMe' => true])->assertStatus(400)->assertJsonPath('success', false)->assertJsonStructure(['message', 'requestId', 'timestamp']);
    $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'invalid'])->assertStatus(400);
});
it('expires real tokens and enforces current roles and permissions', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['password' => 'Fixed TTL Password!']);
    $user->assignRole('USER');
    $token = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Fixed TTL Password!'])->assertOk()->json('data.accessToken');
    $this->withToken($token)->getJson('/api/v1/admin/settings')->assertForbidden();
    $this->travel(481)->minutes();
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});
it('keeps refresh and booking draft routes absent and the old submission route retired', function (): void {
    $this->postJson('/api/v1/auth/refresh/token')->assertNotFound();
    $this->postJson('/api/v1/portal/bookings')->assertNotFound();
    $this->postJson('/api/v1/portal/submissions')->assertStatus(410);
    $this->postJson('/api/v1/portal/client', [])->assertUnauthorized();
});
it('disables registration unless explicitly configured and rejects role escalation', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $input = ['fullName' => 'Test Buyer', 'email' => 'buyer@example.com', 'password' => 'Secure Password!', 'confirmPassword' => 'Secure Password!'];
    $this->postJson('/api/v1/auth/register', $input)->assertForbidden();
    config(['portal.registration_enabled' => true]);
    $this->postJson('/api/v1/auth/register', $input + ['role' => 'ADMIN'])->assertStatus(400);
    $this->postJson('/api/v1/auth/register', $input)->assertCreated()->assertJsonPath('data.role', 'USER');
    $this->assertDatabaseHas('users', ['email' => 'buyer@example.com']);
});
it('accepts an imported Node bcrypt hash and enforces the UTF-8 password byte limit', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['email' => 'imported@example.com']);
    $hash = str_replace('$2y$', '$2b$', Hash::make('Node Password!'));
    DB::table('users')->where('id', $user->id)->update(['password' => $hash]);
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Node Password!'])->assertOk();
    config(['portal.registration_enabled' => true]);
    $long = str_repeat('é', 40);
    $this->postJson('/api/v1/auth/register', ['fullName' => 'New User', 'email' => 'new@example.com', 'password' => $long, 'confirmPassword' => $long])->assertStatus(400);
});
it('prevents removing the last usable administrator and audits successful role assignments', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('ADMIN');
    $buyer = User::factory()->create();
    $buyer->assignRole('USER');
    $this->withToken($admin->createToken('test')->plainTextToken);
    $this->postJson('/api/v1/admin/roles/assign', ['userId' => $admin->id, 'role' => 'USER'])->assertConflict();
    expect($admin->fresh()->hasRole('ADMIN'))->toBeTrue();
    $this->assertDatabaseCount('audit_logs', 0);
    $this->postJson('/api/v1/admin/roles/assign', ['userId' => $buyer->id, 'role' => 'ADMIN'])->assertOk();
    expect($buyer->fresh()->hasRole('ADMIN'))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'ASSIGN_ROLE', 'local_entity_id' => $buyer->id]);
    $role = Role::findByName('ADMIN', 'web');
    $this->patchJson('/api/v1/admin/roles/'.$role->id.'/permissions', ['permissions' => ['content.manage']])->assertConflict();
    expect($role->fresh()->hasPermissionTo('roles.manage'))->toBeTrue();
});
