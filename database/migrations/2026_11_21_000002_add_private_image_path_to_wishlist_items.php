<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('wishlist_items', 'image_path')) {
            Schema::table('wishlist_items', function (Blueprint $table) {
                $table->string('image_path')->nullable()->after('image_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('wishlist_items', 'image_path')) {
            Schema::table('wishlist_items', fn (Blueprint $table) => $table->dropColumn('image_path'));
        }
    }
};
