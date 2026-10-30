<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    /**
     * Crea una cuenta activa y verificada para cada rol del sistema.
     *
     * Las cuentas usan el dominio reservado .test y una sola contraseña,
     * configurable mediante VELVET_TEST_USERS_PASSWORD.
     */
    public function run(): void
    {
        // Permite ejecutar este seeder directamente en una base recién migrada.
        $this->call(RolePermissionSeeder::class);

        $password = env('VELVET_TEST_USERS_PASSWORD', 'ChangeMe123!'); 

        $users = [
            ['name' => 'Prueba Super Administrador', 'email' => 'superadmin@velvet.test', 'role' => 'super_admin'],
            ['name' => 'Prueba Administrador', 'email' => 'administrador@velvet.test', 'role' => 'admin'],
            ['name' => 'Prueba Reclutamiento', 'email' => 'reclutamiento@velvet.test', 'role' => 'recruiter'],
            ['name' => 'Prueba Recursos Humanos', 'email' => 'rh@velvet.test', 'role' => 'hr'],
            ['name' => 'Prueba Reclutamiento y Recursos Humanos', 'email' => 'reclutamiento.rh@velvet.test', 'roles' => ['recruiter', 'hr']],
            ['name' => 'Prueba Manager', 'email' => 'manager@velvet.test', 'role' => 'manager'],
            ['name' => 'Prueba Monitor', 'email' => 'monitor@velvet.test', 'role' => 'monitor'],
            ['name' => 'Prueba Modelo', 'email' => 'modelo@velvet.test', 'role' => 'model'],
            ['name' => 'Prueba Candidato', 'email' => 'candidato@velvet.test', 'role' => 'candidate'],
            ['name' => 'Prueba Director de Operaciones', 'email' => 'director.operaciones@velvet.test', 'role' => 'operations_director'],
            ['name' => 'Prueba Marketing', 'email' => 'marketing@velvet.test', 'role' => 'marketing'],
            ['name' => 'Prueba Finanzas', 'email' => 'finanzas@velvet.test', 'role' => 'finance'],
            ['name' => 'Prueba Compliance', 'email' => 'compliance@velvet.test', 'role' => 'compliance'],
            ['name' => 'Prueba Soporte', 'email' => 'soporte@velvet.test', 'role' => 'support'],
        ];

        foreach ($users as $definition) {
            $user = User::updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => Hash::make($password),
                    'is_active' => true,
                ]
            );

            // email_verified_at no es un atributo asignable en el modelo User.
            $user->forceFill(['email_verified_at' => now()])->save();

            // Reejecutar el seeder deja exactamente los roles definidos para cada cuenta.
            $user->syncRoles($definition['roles'] ?? [$definition['role']]);
        }

        $this->command?->info('14 usuarios de prueba creados o actualizados.');
    }
}
