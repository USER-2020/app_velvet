<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_stations', function (Blueprint $table) {
            $table->json('obs_status')->nullable()->after('configuration_version');
            $table->json('system_metrics')->nullable()->after('obs_status');
            $table->timestamp('obs_reported_at')->nullable()->after('system_metrics');
        });

        DB::table('agent_stations')->orderBy('id')->get()->each(function (object $station): void {
            $heartbeat = DB::table('agent_heartbeats')
                ->where('station_id', $station->id)
                ->latest('received_at')
                ->first();

            if (! $heartbeat) {
                return;
            }

            $payload = json_decode($heartbeat->payload, true);
            $status = data_get($payload, 'status');

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
        Schema::table('agent_stations', function (Blueprint $table) {
            $table->dropColumn(['obs_status', 'system_metrics', 'obs_reported_at']);
        });
    }
};
