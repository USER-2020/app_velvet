<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL may commit earlier CREATE TABLE statements before an index
        // error. Guard each table so a corrected migration can be rerun safely.
        $create = function (string $name, \Closure $definition): void {
            if (! Schema::hasTable($name)) {
                Schema::create($name, $definition);
            }
        };

        $create('platform_catalogs', function (Blueprint $table): void {
            $table->id(); $table->string('code', 50)->unique(); $table->string('name', 120); $table->string('adapter_key', 80); $table->boolean('is_active')->default(true); $table->json('metadata')->nullable(); $table->timestamps();
        });
        $create('studio_accounts', function (Blueprint $table): void {
            $table->id(); $table->foreignId('platform_catalog_id')->constrained()->restrictOnDelete(); $table->string('name', 120); $table->string('legal_holder', 180)->nullable(); $table->string('payment_receiver', 180)->nullable(); $table->string('account_type', 30)->default('STUDIO'); $table->string('status', 20)->default('UNCONFIGURED')->index(); $table->string('connection_mode', 20)->default('MANUAL'); $table->text('encrypted_credentials')->nullable(); $table->timestamp('credentials_verified_at')->nullable(); $table->timestamp('last_synced_at')->nullable(); $table->text('last_error')->nullable(); $table->json('metadata')->nullable(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->index(['platform_catalog_id', 'status']);
        });
        $create('model_platform_accounts', function (Blueprint $table): void {
            $table->id(); $table->foreignId('candidate_id')->constrained()->restrictOnDelete(); $table->foreignId('studio_account_id')->constrained()->cascadeOnDelete(); $table->string('account_identifier', 180); $table->string('ownership_type', 30)->default('STUDIO'); $table->string('status', 20)->default('PENDING')->index(); $table->boolean('is_authorized')->default(false); $table->timestamp('linked_at')->nullable(); $table->timestamp('unlinked_at')->nullable(); $table->text('notes')->nullable(); $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->unique(['studio_account_id', 'account_identifier'], 'mpa_studio_account_identifier_uq');
        });
        $create('platform_import_batches', function (Blueprint $table): void {
            $table->id(); $table->foreignId('studio_account_id')->constrained()->cascadeOnDelete(); $table->string('source', 20); $table->string('status', 20)->default('PENDING'); $table->string('original_name', 255)->nullable(); $table->unsignedInteger('rows_total')->default(0); $table->unsignedInteger('rows_imported')->default(0); $table->json('errors')->nullable(); $table->json('payload')->nullable(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
        $create('platform_sync_runs', function (Blueprint $table): void {
            $table->id(); $table->foreignId('studio_account_id')->constrained()->cascadeOnDelete(); $table->string('mode', 20); $table->string('status', 20); $table->json('summary')->nullable(); $table->text('error_message')->nullable(); $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('started_at'); $table->timestamp('finished_at')->nullable(); $table->timestamps();
        });
        $create('operational_sessions', function (Blueprint $table): void {
            $table->id(); $table->foreignId('operational_shift_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('candidate_id')->constrained()->restrictOnDelete(); $table->foreignId('monitor_candidate_id')->nullable()->constrained('candidates')->nullOnDelete(); $table->string('status', 20)->default('OPEN')->index(); $table->dateTime('started_at'); $table->dateTime('ended_at')->nullable(); $table->string('source', 20)->default('MANUAL'); $table->text('notes')->nullable(); $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->index(['candidate_id', 'started_at']);
        });
        $create('stream_sessions', function (Blueprint $table): void {
            $table->id(); $table->foreignId('operational_session_id')->constrained()->cascadeOnDelete(); $table->foreignId('model_platform_account_id')->nullable()->constrained()->nullOnDelete(); $table->string('platform_name', 120); $table->string('status', 20)->default('OPEN')->index(); $table->dateTime('started_at'); $table->dateTime('ended_at')->nullable(); $table->string('data_origin', 20)->default('MANUAL'); $table->json('metrics')->nullable(); $table->text('notes')->nullable(); $table->timestamps(); $table->index(['operational_session_id', 'started_at']);
        });
        $create('production_metrics', function (Blueprint $table): void {
            $table->id(); $table->foreignId('operational_session_id')->nullable()->constrained()->cascadeOnDelete(); $table->foreignId('stream_session_id')->nullable()->constrained()->cascadeOnDelete(); $table->string('metric_key', 80); $table->decimal('metric_value', 14, 2); $table->string('unit', 30)->default('COUNT'); $table->string('origin', 20)->default('MANUAL'); $table->timestamp('recorded_at'); $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete(); $table->boolean('is_validated')->default(false); $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('validated_at')->nullable(); $table->text('correction_reason')->nullable(); $table->timestamps(); $table->index(['stream_session_id', 'metric_key']);
        });
        $create('branches', function (Blueprint $table): void {
            $table->id(); $table->string('code', 50)->unique(); $table->string('name', 120); $table->string('address', 255)->nullable(); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        $create('inventory_assets', function (Blueprint $table): void {
            $table->id(); $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete(); $table->string('asset_tag', 80)->unique(); $table->string('name', 160); $table->string('category', 60); $table->string('status', 20)->default('AVAILABLE')->index(); $table->string('serial_number', 120)->nullable(); $table->date('acquired_at')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        $create('maintenance_records', function (Blueprint $table): void {
            $table->id(); $table->foreignId('inventory_asset_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete(); $table->string('kind', 20); $table->string('status', 20)->default('SCHEDULED'); $table->dateTime('scheduled_at')->nullable(); $table->dateTime('completed_at')->nullable(); $table->text('description'); $table->text('resolution')->nullable(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
        $create('performance_reviews', function (Blueprint $table): void {
            $table->id(); $table->foreignId('candidate_id')->constrained()->restrictOnDelete(); $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $table->date('reviewed_on'); $table->unsignedTinyInteger('score')->nullable(); $table->text('summary'); $table->json('goals')->nullable(); $table->timestamps();
        });
        $create('finance_export_batches', function (Blueprint $table): void {
            $table->id(); $table->string('status', 20)->default('PENDING'); $table->string('format', 20)->default('JSON'); $table->json('payload'); $table->string('checksum', 64); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('exported_at')->nullable(); $table->timestamps();
        });

        if (Schema::hasTable('model_platform_accounts') && DB::getDriverName() === 'mysql') {
            $indexes = DB::select('SHOW INDEX FROM model_platform_accounts');
            $hasUnique = collect($indexes)->contains(fn ($index) => ($index->Key_name ?? null) === 'mpa_studio_account_identifier_uq');

            if (! $hasUnique) {
                Schema::table('model_platform_accounts', fn (Blueprint $table) => $table->unique(['studio_account_id', 'account_identifier'], 'mpa_studio_account_identifier_uq'));
            }
        }
    }
    public function down(): void
    {
        foreach (['finance_export_batches','performance_reviews','maintenance_records','inventory_assets','branches','production_metrics','stream_sessions','operational_sessions','platform_sync_runs','platform_import_batches','model_platform_accounts','studio_accounts','platform_catalogs'] as $table) Schema::dropIfExists($table);
    }
};
