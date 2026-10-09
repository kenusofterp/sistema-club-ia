<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Instalación limpia (php artisan migrate:fresh --seed): solo el super administrador del .env
     * y el catálogo de permisos. Entidades, roles, usuarios y todo lo demás los carga el operador.
     * Datos de demostración: php artisan db:seed --class=DemoSeeder (o AcademyDemoSeeder).
     */
    public function run(): void
    {
        $this->call([
            PermissionsSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
