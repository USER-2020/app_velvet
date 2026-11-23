<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('candidates', 'wishlist_share_token')) {
            Schema::table('candidates', function (Blueprint $table) {
                $table->string('wishlist_share_token', 80)->nullable()->unique()->after('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('candidates', 'wishlist_share_token')) {
            Schema::table('candidates', function (Blueprint $table) {
                $table->dropUnique('candidates_wishlist_share_token_unique');
                $table->dropColumn('wishlist_share_token');
            });
        }
    }
};
