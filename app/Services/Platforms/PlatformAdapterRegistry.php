<?php

namespace App\Services\Platforms;

use App\Models\StudioAccount;

class PlatformAdapterRegistry
{
    public function for(StudioAccount $account): PlatformAdapter
    {
        // No adapter real is registered until the studio supplies official credentials and documentation.
        return new SimulationPlatformAdapter();
    }
}
