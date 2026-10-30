<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_enrollment_tokens', function (Blueprint $t) {
            $t->id();
            $t->string('token_hash', 64)->unique();
            $t->string('studio_id', 100);
            $t->foreignId('issued_by')->constrained('users');
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });
        Schema::create('agent_stations', function (Blueprint $t) {
            $t->id();
            $t->uuid('installation_id')->unique();
            $t->string('hostname');
            $t->string('studio_id', 100);
            $t->string('credential_hash', 64)->unique();
            $t->json('abilities');
            $t->string('version', 100)->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('offline_at')->nullable();
            $t->json('configuration');
            $t->unsignedInteger('configuration_version')->default(1);
            $t->timestamps();
        });
        foreach (['heartbeats', 'events'] as $type) {
            Schema::create('agent_'.$type, function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignId('station_id')->constrained('agent_stations');
                $t->string('body_hash', 64);
                $t->json('payload');
                $t->dateTime('sent_at', 6);
                $t->timestamp('received_at');
            });
        }
        Schema::create('agent_commands', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('station_id')->constrained('agent_stations');
            $t->string('operation');
            $t->string('state')->default('pending');
            $t->timestamp('expires_at');
            $t->foreignId('issued_by')->constrained('users');
            $t->json('result')->nullable();
            $t->string('result_hash', 64)->nullable();
            $t->timestamps();
        });
        Schema::create('agent_idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->string('scope', 180);
            $t->string('key', 100);
            $t->string('body_hash', 64);
            $t->text('response')->nullable();
            $t->unsignedSmallInteger('status')->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->unique(['scope', 'key']);
        });
    }

    public function down(): void
    {
        foreach (['agent_idempotency_keys', 'agent_commands', 'agent_events', 'agent_heartbeats', 'agent_stations', 'agent_enrollment_tokens'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
