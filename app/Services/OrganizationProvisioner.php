<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Setting;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Prepara una entidad nueva: solo su configuración. Arranca vacía (sin actividades, instalaciones,
 * planes ni contenido del sitio de ejemplo); todo se carga desde el panel. Es idempotente.
 * Contenido de ejemplo, si se quiere: ClubBaseSeeder, GymSeeder y SiteContentSeeder.
 */
class OrganizationProvisioner
{
    public function provision(Organization $organization): void
    {
        Organization::runFor($organization, function (Organization $org) {
            DB::transaction(function () use ($org) {
                $isNew = Setting::query()->doesntExist();

                app(SettingsSeeder::class)->run();

                if ($isNew) {
                    Setting::set('site.name', $org->name);
                    Setting::set('site.short_name', mb_strtoupper(mb_substr(preg_replace('/[^\pL\pN]/u', '', $org->name), 0, 3)));
                }
            });
        });
    }
}
