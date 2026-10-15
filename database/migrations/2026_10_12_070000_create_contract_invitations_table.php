<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('invitation_expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->string('status', 20)->default('INVITED')->index();
            $table->timestamps();
            $table->index(['candidate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_invitations');
    }
};
