<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('agent_stations')->orderBy('id')->get()->each(function (object $station): void {
            $heartbeat = DB::table('agent_heartbeats')
                ->where('station_id', $station->id)
                ->latest('received_at')
                ->first();

            if (! $heartbeat) {
                return;
            }

            $status = data_get(json_decode($heartbeat->payload, true), 'status');
            if (! is_array($status)) {
                return;
            }

            DB::table('agent_stations')->where('id', $station->id)->update([
                'obs_status' => json_encode(data_get($status, 'obs')),
                'system_metrics' => json_encode(data_get($status, 'metrics')),
                'obs_reported_at' => $heartbeat->received_at,
            ]);
        });
    }

    public function down(): void
    {
        // Snapshot values remain valid if this data-repair migration is rolled back.
    }
};
