<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('headline', 300)->nullable();
            $table->text('about')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('visa_status')->nullable();
            $table->text('avatar_url')->nullable();
            $table->timestampsTz();
        });
        Schema::create('otps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('otp_hash');
            $table->string('type');
            $table->timestampTz('expires_at')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(5);
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'type', 'created_at']);
        });
        Schema::create('password_reset_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
        });
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('object_key')->unique();
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum');
            $table->text('url');
            $table->string('alt_text', 300)->default('');
            $table->string('status')->default('PENDING');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();
            $table->index(['status', 'created_at']);
        });
        Schema::create('catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->string('description', 500);
            $table->text('why_it_matters')->default('');
            $table->jsonb('features');
            $table->string('category');
            $table->string('kind')->default('ADDON');
            $table->string('type')->default('SINGLE');
            $table->string('status')->default('DRAFT');
            $table->unsignedInteger('price_cents');
            $table->char('currency', 3)->default('USD');
            $table->boolean('recommended')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('isn_service_ids');
            $table->string('isn_package_id')->nullable();
            $table->string('isn_order_type_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->index(['status', 'kind', 'category', 'sort_order']);
        });
        Schema::create('content_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('kind');
            $table->string('title', 200);
            $table->text('body')->default('');
            $table->text('url')->nullable();
            $table->string('category')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('DRAFT');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
            $table->index(['status', 'kind', 'category']);
        });
        foreach (['catalog_media' => ['item_id', 'catalog_items'], 'content_media' => ['entry_id', 'content_entries']] as $name => [$key,$target]) {
            Schema::create($name, function (Blueprint $table) use ($key, $target): void {
                $table->foreignId($key)->constrained($target)->cascadeOnDelete();
                $table->foreignId('media_id')->constrained('media_assets')->restrictOnDelete();
                $table->unsignedInteger('position')->default(0);
                $table->primary([$key, 'media_id']);
            });
        }
        Schema::create('recommendation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->jsonb('conditions');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->index(['enabled', 'sort_order']);
        });
        Schema::create('portal_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('brand_name')->default('At Ease Inspections');
            $table->string('help_phone')->default('770-765-6869');
            $table->text('welcome_title')->default('A home decision, made clearer.');
            $table->text('pricing_disclaimer')->default('Fees are estimates. Final pricing confirmed before your appointment.');
            $table->text('confirmation_message')->default('Your inspection request is on its way to our scheduling team. Our team will confirm your time and inspector details.');
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('updated_at')->nullable();
        });
        Schema::create('quote_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->char('preview_token', 64)->unique();
            $table->string('pricing_source')->default('ISN');
            $table->string('category');
            $table->jsonb('property');
            $table->jsonb('catalog_ids');
            $table->jsonb('catalog_versions');
            $table->jsonb('service_ids');
            $table->string('package_id')->nullable();
            $table->string('order_type_id');
            $table->jsonb('lines');
            $table->integer('total_cents');
            $table->integer('duration_minutes')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('created_at')->useCurrent();
        });
        Schema::create('order_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->string('idempotency_key', 128)->unique();
            $table->char('request_hash', 64);
            $table->string('hash_version')->default('php-canonical-v1');
            $table->foreignId('quote_id')->unique()->constrained('quote_snapshots')->restrictOnDelete();
            $table->jsonb('request_data');
            $table->string('status')->default('PROCESSING');
            $table->string('phase')->default('PREPARING');
            $table->string('client_id')->nullable();
            $table->string('agent_id')->nullable();
            $table->string('remote_order_id')->nullable()->unique();
            $table->bigInteger('remote_order_number')->nullable();
            $table->string('error_code')->nullable();
            $table->boolean('notification_pending')->default(false);
            $table->timestampTz('notification_sent_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'updated_at']);
            $table->index('notification_pending');
        });
        Schema::create('legacy_id_maps', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type');
            $table->string('old_id');
            $table->unsignedBigInteger('new_id');
            $table->unique(['entity_type', 'old_id']);
        });
        Schema::create('booking_drafts', function (Blueprint $table): void {
            $table->id();
            $table->string('token_hash');
            $table->jsonb('data');
            $table->string('current_step')->default('WHO_ITS_FOR');
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('expires_at');
            $table->string('archival_quote_id')->nullable();
            $table->string('status')->default('DRAFT');
            $table->string('submission_key')->nullable()->unique();
            $table->timestampsTz();
        });
        Schema::create('booking_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('booking_drafts')->restrictOnDelete();
            $table->string('reference')->unique();
            $table->string('idempotency_key')->unique();
            $table->jsonb('payload');
            $table->integer('estimated_total_cents');
            $table->string('status')->default('PENDING_CONFIGURATION');
            $table->string('remote_submission_key')->unique();
            $table->string('archival_integration_quote_id')->nullable();
            $table->jsonb('sync_input')->nullable();
            $table->string('lease_id')->nullable();
            $table->timestampTz('lease_expires_at')->nullable();
            $table->string('error_code')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        foreach (['booking_completions', 'booking_drafts', 'legacy_id_maps', 'order_submissions', 'quote_snapshots', 'portal_settings', 'recommendation_rules', 'content_media', 'catalog_media', 'content_entries', 'catalog_items', 'media_assets', 'password_reset_grants', 'otps', 'user_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
