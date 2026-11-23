<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('model_projections', 'urgency')) {
            Schema::table('model_projections', function (Blueprint $table) {
                $table->string('urgency', 20)->default('medium')->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('model_projections', 'urgency')) {
            Schema::table('model_projections', fn (Blueprint $table) => $table->dropColumn('urgency'));
        }
    }
};
