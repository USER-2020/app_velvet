<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_stations', function (Blueprint $table): void {
            $table->foreignId('room_id')->nullable()->after('studio_id')->constrained()->nullOnDelete();
            $table->index(['room_id', 'revoked_at']);
        });

        Schema::create('station_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('station_id')->constrained('agent_stations')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('model_candidate_id')->constrained('candidates')->restrictOnDelete();
            $table->foreignId('monitor_candidate_id')->nullable()->constrained('candidates')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable()->index();
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['station_id', 'starts_at', 'ends_at']);
            $table->index(['room_id', 'starts_at', 'ends_at']);
            $table->index(['model_candidate_id', 'starts_at', 'ends_at']);
            $table->index(['monitor_candidate_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_assignments');

        Schema::table('agent_stations', function (Blueprint $table): void {
            $table->dropForeign(['room_id']);
            $table->dropIndex(['room_id', 'revoked_at']);
            $table->dropColumn('room_id');
        });
    }
};
