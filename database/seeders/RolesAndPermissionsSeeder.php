<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Catálogo de permisos más los roles sugeridos (administrador, tesorero, profesor…). No lo usa la
 * instalación limpia: lo usan los tests y los datos de demostración.
 * El super administrador no usa roles: se marca con users.is_super_admin.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $new = app(PermissionsSeeder::class)->syncCatalog();

        $registrar = app(PermissionRegistrar::class);
        $previousTeam = getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null);

        foreach (Permissions::defaultRoles() as $name => $definition) {
            $role = Role::findOrCreate($name, 'web');

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($definition['permissions']);
            } else {
                // Rol existente: solo se agregan los permisos nuevos de esta versión,
                // respetando los cambios hechos desde el sistema.
                $role->givePermissionTo(array_values(array_intersect($definition['permissions'], $new)));
            }
        }

        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId($previousTeam);
    }
}
