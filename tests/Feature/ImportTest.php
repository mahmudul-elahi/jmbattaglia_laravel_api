<?php

use App\Models\CatalogItem;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\LegacyImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);
it('imports integer IDs and preserves provider identifiers and bcrypt hashes on repeated runs', function (): void {
    config(['portal.mail_disabled' => true, 'isn.writes_enabled' => false]);
    $hash = Hash::make('Imported Password!');
    $data = ['Role' => [['id' => 'old-admin', 'name' => 'ADMIN']], 'Permission' => [['id' => 'old-permission', 'name' => 'content.manage']], 'RolePermission' => [['roleId' => 'old-admin', 'permissionId' => 'old-permission']],
        'User' => [['id' => 'old-user', 'email' => 'ADMIN@EXAMPLE.COM', 'password' => $hash, 'provider' => 'local', 'roleId' => 'old-admin', 'isEmailVerified' => true, 'createdAt' => now(), 'updatedAt' => now()]],
        'UserProfile' => [['id' => 'old-profile', 'userId' => 'old-user', 'firstName' => 'Dana', 'createdAt' => now(), 'updatedAt' => now()]],
        'MediaAsset' => [['id' => 'old-media', 'objectKey' => 'portal/existing.png', 'filename' => 'existing.png', 'mimeType' => 'image/png', 'sizeBytes' => 100, 'checksum' => 'hash', 'url' => 'https://cdn.example.com/existing.png', 'createdBy' => 'old-user', 'status' => 'READY', 'expiresAt' => now(), 'createdAt' => now(), 'updatedAt' => now()]],
        'CatalogItem' => [['id' => 'old-addon', 'slug' => 'radon', 'name' => 'Radon', 'description' => 'Radon testing', 'whyItMatters' => 'Indoor air', 'features' => [], 'category' => 'RESALE_HOME', 'kind' => 'ADDON', 'type' => 'SINGLE', 'priceCents' => 10000, 'isnServiceIds' => ['provider-uuid-kept'], 'createdAt' => now(), 'updatedAt' => now()]],
        'CatalogMedia' => [['itemId' => 'old-addon', 'mediaId' => 'old-media', 'position' => 0]],
        'UserSession' => [['id' => 'retired-session', 'refreshTokenHash' => 'never-import']]];
    $importer = app(LegacyImportService::class);
    $importer->import($data);
    $user = User::first();
    expect($user->id)->toBeInt();
    expect($user->password)->toBe($hash);
    expect($user->hasRole('ADMIN'))->toBeTrue();
    expect(Hash::check('Imported Password!', $user->password))->toBeTrue();
    expect(CatalogItem::first()->isn_service_ids)->toBe(['provider-uuid-kept']);
    expect(CatalogItem::first()->media()->first()->id)->toBe(MediaAsset::first()->id);
    $importer->import($data);
    expect(User::count())->toBe(1);
    expect(CatalogItem::count())->toBe(1);
    expect(DB::table('personal_access_tokens')->count())->toBe(0);
});
it('rolls dry runs back and refuses imports when external writes are enabled', function (): void {
    config(['portal.mail_disabled' => true, 'isn.writes_enabled' => false]);
    app(LegacyImportService::class)->import(['Role' => [['id' => 'source-role', 'name' => 'USER']]], true);
    expect(DB::table('roles')->count())->toBe(0);
    expect(DB::table('legacy_id_maps')->count())->toBe(0);
    config(['isn.writes_enabled' => true]);
    expect(fn () => app(LegacyImportService::class)->import([]))->toThrow(HttpException::class);
});
