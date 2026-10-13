<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('VELVET_ADMIN_EMAIL', 'admin@velvet.local');
        $password = env('VELVET_ADMIN_PASSWORD', 'change-me-now');
    
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => env('VELVET_ADMIN_NAME', 'Velvet Admin'), 'password' => Hash::make($password), 'email_verified_at' => now()]
        );

        $user->assignRole('super_admin');
    }
}
