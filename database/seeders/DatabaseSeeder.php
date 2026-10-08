<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Services\OrganizationProvisioner;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Instalación base: roles, superusuario y la entidad principal.
     * Más entidades se crean desde Administración > Entidades.
     * Datos de demostración (varias entidades y socios compartidos): php artisan db:seed --class=DemoSeeder
     */
    public function run(OrganizationProvisioner $provisioner): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
        ]);

        $organization = Organization::firstOrCreate(['slug' => 'principal'], [
            'name' => config('club.default_organization.name'),
            'type' => config('club.default_organization.type'),
            'is_default' => true,
        ]);

        $provisioner->provision($organization);
    }
}
