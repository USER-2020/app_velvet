<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_tour_progresses')) {
            return;
        }

        Schema::create('user_tour_progresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tour_key', 100);
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tour_key']);
        });
    }

    public function down(): void
    {
        // La migración original conserva la propiedad de esta tabla.
    }
};
