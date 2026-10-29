<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launcher_enrollment_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('studio_id', 100);
            $table->foreignId('issued_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('launcher_stations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('installation_id')->unique();
            $table->string('hostname', 255);
            $table->string('operating_system', 255)->nullable();
            $table->string('studio_id', 100);
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('credential_hash', 64)->unique();
            $table->string('version', 100)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('configuration');
            $table->unsignedInteger('configuration_version')->default(1);
            $table->timestamps();
            $table->index(['room_id', 'revoked_at'], 'ls_room_revoked_idx');
        });

        Schema::create('launcher_heartbeats', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('station_id')->constrained('launcher_stations')->cascadeOnDelete();
            $table->string('body_hash', 64);
            $table->json('payload');
            $table->dateTime('sent_at', 6);
            $table->timestamp('received_at');
            $table->index(['station_id', 'received_at'], 'lh_station_received_idx');
        });

        Schema::create('launcher_station_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('station_id')->constrained('launcher_stations')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('model_candidate_id')->constrained('candidates')->restrictOnDelete();
            $table->foreignId('monitor_candidate_id')->nullable()->constrained('candidates')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable()->index();
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['station_id', 'starts_at', 'ends_at'], 'lsa_station_time_idx');
            $table->index(['room_id', 'starts_at', 'ends_at'], 'lsa_room_time_idx');
            $table->index(['model_candidate_id', 'starts_at', 'ends_at'], 'lsa_model_time_idx');
            $table->index(['monitor_candidate_id', 'starts_at', 'ends_at'], 'lsa_monitor_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('launcher_station_assignments');
        Schema::dropIfExists('launcher_heartbeats');
        Schema::dropIfExists('launcher_stations');
        Schema::dropIfExists('launcher_enrollment_tokens');
    }
};
