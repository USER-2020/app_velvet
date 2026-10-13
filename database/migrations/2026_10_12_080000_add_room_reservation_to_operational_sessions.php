<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operational_sessions') || Schema::hasColumn('operational_sessions', 'room_reservation_id')) {
            return;
        }

        Schema::table('operational_sessions', function (Blueprint $table): void {
            $table->foreignId('room_reservation_id')->nullable()->unique()->constrained('room_reservations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('operational_sessions') || ! Schema::hasColumn('operational_sessions', 'room_reservation_id')) {
            return;
        }

        Schema::table('operational_sessions', function (Blueprint $table): void {
            $table->dropForeign(['room_reservation_id']);
            $table->dropUnique(['room_reservation_id']);
            $table->dropColumn('room_reservation_id');
        });
    }
};
