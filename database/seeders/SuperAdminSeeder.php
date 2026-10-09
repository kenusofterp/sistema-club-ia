<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Superusuario de la plataforma: administra todas las entidades. */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('club.superadmin.email');
        $password = config('club.superadmin.password');

        if (! $email || ! $password) {
            throw new RuntimeException('Defina SUPERADMIN_EMAIL y SUPERADMIN_PASSWORD en el archivo .env');
        }

        $user = User::withTrashed()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->fill([
                'name' => 'Super Administrador',
                'password' => $password,
                'is_active' => true,
            ]);
            $user->email_verified_at = now();
        }

        $user->is_super_admin = true;
        $user->deleted_at = null;
        // Instalación limpia: la auditoría arranca vacía.
        activity()->withoutLogging(fn () => $user->save());
    }
}
