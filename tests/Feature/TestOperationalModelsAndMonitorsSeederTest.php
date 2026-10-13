<?php

namespace Tests\Feature;

use Database\Seeders\TestOperationalModelsAndMonitorsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestOperationalModelsAndMonitorsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_complete_idempotent_operational_test_data(): void
    {
        $this->seed(TestOperationalModelsAndMonitorsSeeder::class);
        $this->seed(TestOperationalModelsAndMonitorsSeeder::class);

        $this->assertDatabaseCount('candidates', 5);
        $this->assertDatabaseCount('operational_profiles', 5);
        $this->assertDatabaseCount('model_monitor_assignments', 4);
        $this->assertDatabaseCount('operational_shifts', 2);
        $this->assertDatabaseCount('shift_attendances', 2);
        $this->assertDatabaseHas('candidates', ['code' => 'TEST-MOD-001', 'candidate_type' => 'MODEL', 'status' => 'ACTIVE']);
        $this->assertDatabaseHas('candidates', ['code' => 'TEST-MON-001', 'candidate_type' => 'MONITOR', 'status' => 'ACTIVE']);
        $this->assertDatabaseHas('operational_requests', ['type' => 'SCHEDULE_CHANGE', 'status' => 'PENDING']);
    }
}
