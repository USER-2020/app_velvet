<?php

namespace App\Services\Platforms;

use App\Models\StudioAccount;

class SimulationPlatformAdapter implements PlatformAdapter
{
    public function key(): string { return 'simulation'; }
    public function canConnectLive(): bool { return false; }
    public function simulate(StudioAccount $account): array
    {
        return ['mode' => 'SIMULATED', 'account_id' => $account->id, 'message' => 'Datos simulados: no se realizo ninguna conexion externa.', 'streams' => [['platform' => $account->platform?->name, 'minutes' => 90, 'tokens' => 1250, 'gross_amount' => 62.50]]];
    }
}
