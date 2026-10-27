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
                    Setting::set('site.short_name', $this->shortName($org->name));
                }
            });
        });
    }

    /** Al renombrar la entidad, el nombre del sitio la acompaña salvo que se haya personalizado en la configuración. */
    public function rename(Organization $organization, string $previousName): void
    {
        if ($organization->name === $previousName) {
            return;
        }

        Organization::runFor($organization, function (Organization $org) use ($previousName) {
            if (setting('site.name') === $previousName) {
                Setting::set('site.name', $org->name);
            }
            if (setting('site.short_name') === $this->shortName($previousName)) {
                Setting::set('site.short_name', $this->shortName($org->name));
            }
        });
    }

    private function shortName(string $name): string
    {
        return mb_strtoupper(mb_substr(preg_replace('/[^\pL\pN]/u', '', $name), 0, 3));
    }
}
