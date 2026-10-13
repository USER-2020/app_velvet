<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lead_documents', 'review_note')) {
            return;
        }

        Schema::table('lead_documents', function (Blueprint $table) {
            $table->text('review_note')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('lead_documents', 'review_note')) {
            return;
        }

        Schema::table('lead_documents', function (Blueprint $table) {
            $table->dropColumn('review_note');
        });
    }
};
