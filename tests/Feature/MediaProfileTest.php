<?php

use App\Models\MediaAsset;
use App\Models\User;
use App\Services\MediaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);
it('stores real image bytes and prevents referenced media deletion', function (): void {
    Storage::fake('public');
    config(['portal.media_disk' => 'public']);
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('ADMIN');
    $this->withToken($user->createToken('test')->plainTextToken);
    $file = UploadedFile::fake()->image('photo.png');
    $media = $this->post('/api/v1/admin/media/upload', ['file' => $file], ['Accept' => 'application/json'])->assertCreated()->json('data');
    Storage::disk('public')->assertExists($media['objectKey']);
    $this->patchJson('/api/v1/admin/profile', ['avatarMediaId' => $media['id']])->assertOk()->assertJsonPath('data.avatarUrl', $media['url'])->assertJsonPath('data.requiresReauthentication', false);
    $this->deleteJson('/api/v1/admin/media/'.$media['id'])->assertConflict();
    $this->patchJson('/api/v1/admin/profile', ['removeProfilePicture' => true])->assertOk();
    $this->deleteJson('/api/v1/admin/media/'.$media['id'])->assertOk();
    $this->assertDatabaseHas('media_assets', ['id' => $media['id'], 'status' => 'DELETED']);
});
it('requires current password for sensitive profile changes and revokes tokens atomically', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['email' => 'before@example.com', 'password' => 'Original Password!']);
    $user->assignRole('ADMIN');
    $this->withToken($user->createToken('test')->plainTextToken);
    $this->patchJson('/api/v1/admin/profile', ['email' => 'after@example.com'])->assertUnauthorized();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'before@example.com']);
    $this->patchJson('/api/v1/admin/profile', ['email' => 'after@example.com', 'currentPassword' => 'Original Password!', 'newPassword' => 'Updated Password!', 'confirmPassword' => 'Updated Password!'])->assertOk()->assertJsonPath('data.requiresReauthentication', true);
    expect($user->tokens()->count())->toBe(0);
    expect(Hash::check('Updated Password!', $user->fresh()->password))->toBeTrue();
});
it('finalizes verified upload bytes at a new key that an old signed upload cannot replace', function (): void {
    Storage::fake('s3');
    config(['portal.media_disk' => 's3', 'filesystems.disks.s3.bucket' => 'fixture', 'filesystems.disks.s3.region' => 'us-east-1']);
    $bytes = UploadedFile::fake()->image('signed.png')->get();
    $pending = 'portal/pending/fixture.png';
    Storage::disk('s3')->put($pending, $bytes);
    $media = MediaAsset::factory()->create(['object_key' => $pending, 'mime_type' => 'image/png', 'size_bytes' => strlen($bytes), 'checksum' => base64_encode(hash('sha256', $bytes, true)), 'status' => 'PENDING', 'expires_at' => now()->addMinutes(10)]);
    $completed = app(MediaService::class)->complete($media->id);
    expect($completed->object_key)->not->toBe($pending);
    expect($completed->status->value)->toBe('READY');
    Storage::disk('s3')->put($pending, 'changed after validation');
    expect(Storage::disk('s3')->get($completed->object_key))->toBe($bytes);
    expect(app(MediaService::class)->complete($media->id)->object_key)->toBe($completed->object_key);
});
it('cleans retired media and stale staging objects while preserving ready referenced files', function (): void {
    Storage::fake('public');
    config(['portal.media_disk' => 'public']);
    $disk = Storage::disk('public');
    $retired = MediaAsset::factory()->create(['object_key' => 'portal/retired.png', 'status' => 'DELETED', 'updated_at' => now()->subDays(8)]);
    $disk->put($retired->object_key, 'old bytes');
    $ready = MediaAsset::factory()->create(['object_key' => 'portal/ready/current.png']);
    $disk->put($ready->object_key, 'current bytes');
    $staged = 'portal/pending/old.png';
    $disk->put($staged, 'old staging bytes');
    touch($disk->path($staged), now()->subDays(8)->timestamp);
    $this->artisan('portal:cleanup')->assertSuccessful();
    $disk->assertMissing($retired->object_key);
    $disk->assertMissing($staged);
    $disk->assertExists($ready->object_key);
});
it('signs S3 uploads with the expected checksum and ten minute expiry without a live bucket', function (): void {
    config(['portal.media_disk' => 's3', 'filesystems.disks.s3' => ['driver' => 's3', 'key' => 'fixture-access', 'secret' => 'fixture-secret', 'region' => 'us-east-1', 'bucket' => 'fixture-bucket', 'endpoint' => 'https://s3.fixture.test', 'use_path_style_endpoint' => true, 'throw' => true]]);
    Storage::forgetDisk('s3');
    $user = User::factory()->create();
    $checksum = base64_encode(hash('sha256', 'fixture bytes', true));
    $signed = app(MediaService::class)->signed(['filename' => 'photo.png', 'mimeType' => 'image/png', 'sizeBytes' => 13, 'checksum' => $checksum], $user->id);
    parse_str(parse_url($signed['uploadUrl'], PHP_URL_QUERY), $query);
    $headers = array_change_key_case($signed['headers'], CASE_LOWER);
    expect((int) $query['X-Amz-Expires'])->toBe(600);
    expect($query['x-amz-checksum-sha256'] ?? ($headers['x-amz-checksum-sha256'][0] ?? null))->toBe($checksum);
    expect($signed['media']['status'])->toBe('PENDING');
    $this->assertDatabaseHas('media_assets', ['id' => $signed['media']['id'], 'status' => 'PENDING', 'checksum' => $checksum]);
});
