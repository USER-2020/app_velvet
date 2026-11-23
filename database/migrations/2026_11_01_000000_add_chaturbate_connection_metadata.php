<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('studio_accounts') && ! Schema::hasColumn('studio_accounts', 'provider_key')) {
            Schema::table('studio_accounts', function (Blueprint $table): void {
                $table->string('provider_key', 80)->nullable()->after('platform_catalog_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('studio_accounts') && Schema::hasColumn('studio_accounts', 'provider_key')) {
            Schema::table('studio_accounts', fn (Blueprint $table) => $table->dropColumn('provider_key'));
        }
    }
};
