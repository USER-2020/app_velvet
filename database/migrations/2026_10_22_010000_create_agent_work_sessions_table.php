<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_work_sessions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('station_id')->constrained('agent_stations');
            $t->uuid('installation_id');
            $t->string('studio_id', 100);
            $t->timestamp('started_at');
            $t->timestamp('last_heartbeat_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->string('end_reason', 50)->nullable();
            $t->string('status', 20)->default('active');
            $t->unsignedBigInteger('total_connected_seconds')->default(0);
            $t->unsignedBigInteger('total_streaming_seconds')->default(0);
            $t->boolean('last_streaming')->default(false);
            $t->json('last_obs')->nullable();
            $t->timestamp('last_obs_at')->nullable();
            $t->timestamps();
            $t->index(['station_id', 'status']);
            $t->index(['user_id', 'status']);
            $t->index(['installation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_work_sessions');
    }
};
