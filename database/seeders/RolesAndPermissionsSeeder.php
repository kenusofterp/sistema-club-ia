<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles globales (definidos una vez para toda la plataforma) que se asignan a cada usuario por entidad.
 * El super administrador no usa roles: se marca con users.is_super_admin.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
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
