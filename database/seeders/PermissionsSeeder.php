<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Catálogo de permisos del sistema (App\Support\Permissions). No son datos del operador:
 * son la lista fija de permisos con la que se arman los roles desde Administración > Roles.
 */
class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->syncCatalog();
    }

    /** @return array<int, string> permisos creados en esta ejecución */
    public function syncCatalog(): array
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $new = [];
        foreach (Permissions::all() as $permission) {
            if (Permission::findOrCreate($permission, 'web')->wasRecentlyCreated) {
                $new[] = $permission;
            }
        }

        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId($previousTeam);

        return $new;
    }
}
