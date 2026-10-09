<?php

namespace App\Livewire\Admin\Roles;

use App\Livewire\Concerns\InteractsWithUi;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles y sus permisos. Los roles son comunes a todas las entidades; lo que es por entidad es
 * la asignación de roles a cada usuario (en Usuarios).
 */
#[Layout('layouts.admin')]
#[Title('Roles y permisos')]
class Index extends Component
{
    use InteractsWithUi;

    public ?int $roleId = null;

    public string $newRole = '';

    /** @var array<int, string> */
    public array $permissions = [];

    public function mount(): void
    {
        if ($first = Role::orderBy('name')->first()) {
            $this->selectRole($first->id);
        }
    }

    public function selectRole(int $id): void
    {
        $role = Role::with('permissions')->findOrFail($id);
        $this->roleId = $role->id;
        $this->permissions = $role->permissions->pluck('name')->all();
    }

    /** Marca o desmarca todos los permisos (o solo los del grupo en la posición $group). Se aplica al guardar. */
    public function toggleAll(bool $on, ?int $group = null): void
    {
        $scope = $group === null
            ? Permissions::all()
            : array_keys(array_values(Permissions::grouped())[$group] ?? []);

        $this->permissions = $on
            ? array_values(array_unique([...$this->permissions, ...$scope]))
            : array_values(array_diff($this->permissions, $scope));
    }

    public function createRole(): void
    {
        $this->authorize('roles.gestionar');
        $this->validate(['newRole' => ['required', 'alpha_dash', 'max:50', Rule::unique('roles', 'name')]], [], ['newRole' => 'nombre del rol']);

        // organization_id nulo: rol global, asignable en cualquier entidad.
        $role = Role::create(['name' => mb_strtolower($this->newRole), 'guard_name' => 'web', 'organization_id' => null]);
        $this->newRole = '';
        $this->selectRole($role->id);
        $this->notify('Rol creado. Asignale permisos.');
    }

    public function save(): void
    {
        $this->authorize('roles.gestionar');
        $role = Role::findOrFail($this->roleId);

        $this->validate(['permissions.*' => Rule::in(Permissions::all())]);
        $role->syncPermissions($this->permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity('roles')->performedOn($role)->event('updated')->withProperties(['permissions' => $this->permissions])->log("Permisos del rol {$role->name} actualizados");
        $this->notify('Permisos actualizados. Aplican en todas las entidades.');
    }

    public function deleteRole(): void
    {
        $this->authorize('roles.gestionar');
        $role = Role::findOrFail($this->roleId);

        if (DB::table('model_has_roles')->where('role_id', $role->id)->exists()) {
            $this->notify('No se puede eliminar: hay usuarios con este rol en alguna entidad.', 'error');

            return;
        }

        $role->delete();
        $this->reset(['roleId', 'permissions']);
        $this->mount();
        $this->notify('Rol eliminado.');
    }

    public function render()
    {
        return view('livewire.admin.roles.index', [
            'roles' => Role::orderBy('name')->get(),
            'usage' => DB::table('model_has_roles')->selectRaw('role_id, COUNT(DISTINCT model_id) as n')->groupBy('role_id')->pluck('n', 'role_id'),
            'grouped' => Permissions::grouped(),
            'current' => $this->roleId ? Role::find($this->roleId) : null,
        ]);
    }
}
