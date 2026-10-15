<?php

namespace App\Services\Platforms;

use App\Models\StudioAccount;

interface PlatformAdapter
{
    public function key(): string;
    public function canConnectLive(): bool;
    public function simulate(StudioAccount $account): array;
}
