<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Contracting slots are shared capacity. Interview slots remain exclusive.
        DB::table('contract_slots')->where('status', 'BOOKED')->update(['status' => 'AVAILABLE']);
    }

    public function down(): void
    {
        // There is no safe way to infer which shared slots should be BOOKED.
    }
};
