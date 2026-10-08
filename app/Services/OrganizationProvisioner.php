<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Setting;
use Database\Seeders\ClubBaseSeeder;
use Database\Seeders\GymSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Prepara una entidad nueva: configuración, sitio web inicial y datos base según su tipo
 * (categorías y actividades para clubes; clases y planes para gimnasios). Es idempotente.
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

                app(SiteContentSeeder::class)->run();

                if ($org->usesClub()) {
                    app(ClubBaseSeeder::class)->run();
                }

                if ($org->usesGym()) {
                    app(GymSeeder::class)->run();
                }
            });
        });
    }
}
