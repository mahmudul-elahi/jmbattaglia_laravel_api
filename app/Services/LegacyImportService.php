<?php

namespace App\Services;

use App\Models\User;
use App\Support\PortalSerializer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class LegacyImportService
{
    private const TABLES = ['User' => 'users', 'UserProfile' => 'user_profiles', 'MediaAsset' => 'media_assets', 'CatalogItem' => 'catalog_items', 'ContentEntry' => 'content_entries', 'RecommendationRule' => 'recommendation_rules', 'PortalSettings' => 'portal_settings', 'QuoteSnapshot' => 'quote_snapshots', 'OrderSubmission' => 'order_submissions', 'BookingDraft' => 'booking_drafts', 'BookingCompletion' => 'booking_completions'];

    public function import(array $export, bool $dryRun = false): array
    {
        abort_unless(config('portal.mail_disabled') && ! config('isn.writes_enabled'), 409, 'Imports require mail delivery and ISN writes disabled.');
        $allowed = array_merge(array_keys(self::TABLES), ['Role', 'Permission', 'RolePermission', 'CatalogMedia', 'ContentMedia', 'UserSession', 'Otp', 'AuditLog']);
        if (array_diff(array_keys($export), $allowed)) {
            throw new \InvalidArgumentException('Unknown source entity in import.');
        }
        $counts = [];
        DB::beginTransaction();
        try {
            foreach (['Role', 'Permission'] as $entity) {
                foreach ($export[$entity] ?? [] as $row) {
                    $model = $entity === 'Role' ? Role::findOrCreate($row['name'], 'web') : Permission::findOrCreate($row['name'], 'web');
                    $this->saveMap($entity, (string) $row['id'], $model->id);
                }
            }
            foreach (self::TABLES as $entity => $table) {
                $counts[$entity] = 0;
                foreach ($export[$entity] ?? [] as $row) {
                    $oldId = (string) $row['id'];
                    if ($this->mapped($entity, $oldId, false)) {
                        continue;
                    }
                    $attributes = PortalSerializer::attributes($row);
                    unset($attributes['id'],$attributes['role_id']);
                    if ($entity === 'User') {
                        $attributes['email'] = mb_strtolower(trim($row['email']));
                        $attributes['name'] = $row['name'] ?? $row['email'];
                        $attributes['email_verified_at'] = ($row['isEmailVerified'] ?? false) ? ($row['updatedAt'] ?? now()) : null;
                        unset($attributes['is_email_verified']);
                    }
                    foreach (['UserProfile' => ['user_id' => 'User'], 'RecommendationRule' => ['item_id' => 'CatalogItem'], 'OrderSubmission' => ['quote_id' => 'QuoteSnapshot'], 'BookingCompletion' => ['booking_id' => 'BookingDraft']] as $target => $fields) {
                        if ($entity !== $target) {
                            continue;
                        }
                        foreach ($fields as $field => $reference) {
                            $attributes[$field] = $this->mapped($reference, (string) $attributes[$field]);
                        }
                    }
                    if ($entity === 'UserProfile') {
                        $name = trim(($row['firstName'] ?? '').' '.($row['lastName'] ?? ''));
                        if ($name !== '') {
                            DB::table('users')->where('id', $attributes['user_id'])->update(['name' => $name]);
                        }
                    }
                    if ($entity === 'MediaAsset') {
                        $actor = (string) ($row['createdBy'] ?? 'system');
                        $attributes['created_by'] = $this->mapped('User', $actor, false);
                        $attributes['actor_label'] = $attributes['created_by'] ? null : $actor;
                    }
                    if ($entity === 'PortalSettings') {
                        $attributes['id'] = 1;
                        DB::table($table)->where('id', 1)->delete();
                    }
                    if ($entity === 'QuoteSnapshot') {
                        $attributes['preview_token'] = hash('sha256', 'legacy-quote:'.$oldId);
                        unset($attributes['booking_draft_id']);
                    }
                    if ($entity === 'OrderSubmission') {
                        $attributes['hash_version'] = 'legacy-read-only';
                    }
                    if ($entity === 'BookingDraft') {
                        $attributes['archival_quote_id'] = $attributes['quote_id'] ?? null;
                        unset($attributes['quote_id']);
                    }
                    if ($entity === 'BookingCompletion') {
                        $attributes['archival_integration_quote_id'] = $attributes['integration_quote_id'] ?? null;
                        unset($attributes['integration_quote_id']);
                    }
                    $columns = Schema::getColumnListing($table);
                    $attributes = array_intersect_key($attributes, array_flip($columns));
                    foreach ($attributes as $key => $value) {
                        if (is_array($value)) {
                            $attributes[$key] = json_encode($this->remapJson($value, $key), JSON_THROW_ON_ERROR);
                        }
                    }
                    $newId = DB::table($table)->insertGetId($attributes);
                    $this->saveMap($entity, $oldId, (int) $newId);
                    $counts[$entity]++;
                }
            }
            foreach ($export['User'] ?? [] as $row) {
                $user = User::query()->findOrFail($this->mapped('User', (string) $row['id']));
                if (isset($row['roleId'])) {
                    $role = Role::query()->findOrFail($this->mapped('Role', (string) $row['roleId']));
                    $user->syncRoles([$role]);
                }
            }
            foreach ($export['RolePermission'] ?? [] as $row) {
                Role::query()->findOrFail($this->mapped('Role', (string) $row['roleId']))->givePermissionTo(Permission::query()->findOrFail($this->mapped('Permission', (string) $row['permissionId'])));
            }
            foreach (['CatalogMedia' => ['catalog_media', 'item_id', 'CatalogItem'], 'ContentMedia' => ['content_media', 'entry_id', 'ContentEntry']] as $entity => [$table,$field,$parent]) {
                foreach ($export[$entity] ?? [] as $row) {
                    $input = PortalSerializer::attributes($row);
                    DB::table($table)->updateOrInsert([$field => $this->mapped($parent, (string) $input[$field]), 'media_id' => $this->mapped('MediaAsset', (string) $input['media_id'])], ['position' => $input['position'] ?? 0]);
                }
            }
            if ($dryRun) {
                DB::rollBack();
            } else {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("SELECT setval(pg_get_serial_sequence('portal_settings', 'id'), GREATEST((SELECT COALESCE(MAX(id), 1) FROM portal_settings), 1))");
                } DB::commit();
            }
        } catch (\Throwable $error) {
            DB::rollBack();
            throw $error;
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $counts;
    }

    private function saveMap(string $entity, string $oldId, int $newId): void
    {
        DB::table('legacy_id_maps')->updateOrInsert(['entity_type' => $entity, 'old_id' => $oldId], ['new_id' => $newId]);
    }

    private function mapped(string $entity, string $oldId, bool $required = true): ?int
    {
        $id = DB::table('legacy_id_maps')->where('entity_type', $entity)->where('old_id', $oldId)->value('new_id');
        if (! $id && $required) {
            throw new \InvalidArgumentException('Unresolved '.$entity.' source reference.');
        }

        return $id ? (int) $id : null;
    }

    private function remapJson(array $value, string $context = ''): array
    {
        $lists = ['add_on_ids' => 'CatalogItem', 'catalog_ids' => 'CatalogItem', 'catalogIds' => 'CatalogItem', 'addOnIds' => 'CatalogItem', 'selectedIds' => 'CatalogItem', 'mediaIds' => 'MediaAsset'];
        if (isset($lists[$context])) {
            return array_map(fn ($id) => $this->mapped($lists[$context], (string) $id), $value);
        }
        if (in_array($context, ['catalogVersions', 'catalog_versions'], true)) {
            $mapped = [];
            foreach ($value as $id => $version) {
                $mapped[(string) $this->mapped('CatalogItem', (string) $id)] = $version;
            }

            return $mapped;
        }
        if (in_array($context, ['serviceIds', 'service_ids'], true)) {
            return array_map(fn ($id) => $this->mapped('CatalogItem', (string) $id, false) ?? $id, $value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->remapJson($item, (string) $key);
            } elseif (is_string($item) && $key === 'packageId') {
                $value[$key] = $this->mapped('CatalogItem', $item, false) ?? $item;
            } elseif (is_string($item) && in_array($key, ['itemId', 'mediaId', 'avatarMediaId'], true)) {
                $value[$key] = $this->mapped($key === 'itemId' ? 'CatalogItem' : 'MediaAsset', $item);
            }
        }

        return $value;
    }
}
