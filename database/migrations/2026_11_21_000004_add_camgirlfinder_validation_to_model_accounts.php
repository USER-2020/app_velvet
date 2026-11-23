<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_platform_accounts', function (Blueprint $table): void {
            $table->string('nickname_check_status', 30)->nullable()->after('status');
            $table->json('nickname_check_data')->nullable()->after('nickname_check_status');
            $table->timestamp('nickname_checked_at')->nullable()->after('nickname_check_data');
            $table->index(['studio_account_id', 'account_identifier', 'nickname_check_status'], 'mpa_nickname_check_idx');
        });
    }

    public function down(): void
    {
        Schema::table('model_platform_accounts', function (Blueprint $table): void {
            $table->dropIndex('mpa_nickname_check_idx');
            $table->dropColumn(['nickname_check_status', 'nickname_check_data', 'nickname_checked_at']);
        });
    }
};
