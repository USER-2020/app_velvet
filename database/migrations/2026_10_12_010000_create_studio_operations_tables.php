<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_shifts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('model_candidate_id')->constrained('candidates')->restrictOnDelete();
            $table->foreignId('monitor_candidate_id')->nullable()->constrained('candidates')->nullOnDelete();
            $table->foreignId('room_reservation_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->string('status', 20)->default('SCHEDULED')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['model_candidate_id', 'starts_at', 'ends_at']);
            $table->index(['monitor_candidate_id', 'starts_at', 'ends_at']);
        });

        Schema::create('shift_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operational_shift_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('PENDING')->index();
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('checked_out_at')->nullable();
            $table->dateTime('break_started_at')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('room_operation_checklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->index();
            $table->string('status', 20)->default('PENDING')->index();
            $table->json('items');
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['room_id', 'kind', 'status']);
        });

        Schema::create('operational_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operational_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 60);
            $table->string('severity', 20)->default('MEDIUM')->index();
            $table->string('status', 20)->default('OPEN')->index();
            $table->text('description');
            $table->text('resolution')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('operational_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 40);
            $table->string('status', 20)->default('PENDING')->index();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->text('details');
            $table->text('response')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('operational_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operational_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operational_incident_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_notes');
        Schema::dropIfExists('operational_requests');
        Schema::dropIfExists('operational_incidents');
        Schema::dropIfExists('room_operation_checklists');
        Schema::dropIfExists('shift_attendances');
        Schema::dropIfExists('operational_shifts');
    }
};
