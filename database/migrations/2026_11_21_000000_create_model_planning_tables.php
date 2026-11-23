<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('title');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('estimated_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('COP');
            $table->string('priority', 20)->default('medium');
            $table->date('target_date')->nullable();
            $table->string('status', 20)->default('planned');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['candidate_id', 'status']);
        });

        Schema::create('model_projections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 30)->default('goal');
            $table->text('description')->nullable();
            $table->decimal('target_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('COP');
            $table->date('target_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->json('mind_map')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['candidate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_projections');
        Schema::dropIfExists('wishlist_items');
    }
};
