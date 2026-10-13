<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('profile_type', 20)->index();
            $table->string('operational_status', 20)->default('PENDING')->index();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('induction_completed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('model_monitor_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('model_candidate_id')->constrained('candidates')->restrictOnDelete();
            $table->foreignId('monitor_candidate_id')->constrained('candidates')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['model_candidate_id', 'ended_at']);
            $table->index(['monitor_candidate_id', 'ended_at']);
        });

        Schema::create('operational_training_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->index();
            $table->string('title', 180);
            $table->string('status', 20)->default('PENDING')->index();
            $table->boolean('is_required')->default(true);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['candidate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_training_records');
        Schema::dropIfExists('model_monitor_assignments');
        Schema::dropIfExists('operational_profiles');
    }
};
