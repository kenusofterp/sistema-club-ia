<?php

namespace App\Livewire\Admin\Users;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Facility;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * Personal de la entidad actual. Los roles se asignan por entidad: la misma persona puede ser
 * tesorera de un club y recepcionista del gimnasio.
 */
#[Layout('layouts.admin')]
#[Title('Usuarios')]
class Index extends Component
{
    use InteractsWithUi, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public bool $is_active = true;

    public bool $is_super_admin = false;

    public bool $sendInvite = true;

    /** @var array<int, string> roles en la entidad actual */
    public array $roles = [];

    /** @var array<int, string> sedes de la entidad actual donde da clases */
    public array $facilityIds = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->is_active = $user->is_active;
        $this->is_super_admin = $user->is_super_admin;
        $this->roles = $user->roles()->pluck('name')->all();
        $this->facilityIds = $user->facilities()->where('facilities.organization_id', Organization::currentId())->pluck('facilities.id')->map(fn ($id) => (string) $id)->all();
        $this->sendInvite = false;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('usuarios.gestionar');

        $this->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($this->editingId)],
            'phone' => 'nullable|string|max:40',
            'password' => [$this->editingId || $this->sendInvite ? 'nullable' : 'required', PasswordRule::defaults()],
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'roles' => 'array',
            'roles.*' => Rule::exists('roles', 'name'),
            'facilityIds' => 'array',
            'facilityIds.*' => org_exists('facilities'),
        ]);

        $user = $this->editingId ? User::findOrFail($this->editingId) : new User;
        $me = auth()->user();

        if ($user->is($me) && (! $this->is_active || ($me->is_super_admin && ! $this->is_super_admin))) {
            $this->addError('is_active', 'No podés desactivarte ni quitarte el nivel de super administrador a vos mismo.');

            return;
        }

        DB::transaction(function () use ($user, $me) {
            $user->fill([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone ?: null,
                'is_active' => $this->is_active,
            ]);

            // Solo un super administrador puede otorgar o quitar ese nivel.
            if ($me->isSuperAdmin()) {
                $user->is_super_admin = $this->is_super_admin;
            }

            if ($this->password) {
                $user->password = $this->password;
            } elseif (! $user->exists) {
                $user->password = Str::password(32);
            }

            $user->save();
            $user->syncRoles($this->roles); // en la entidad actual

            // Sedes de esta entidad; las de otras entidades no se tocan.
            $here = Facility::withTrashed()->pluck('id');
            $user->facilities()->detach($here->diff(array_map('intval', $this->facilityIds))->all());
            $user->facilities()->syncWithoutDetaching(array_map('intval', $this->facilityIds));

            if (! $this->is_active) {
                $user->tokens()->delete();
            }
        });

        if (! $this->editingId && $this->sendInvite && ! $this->password) {
            Password::sendResetLink(['email' => $user->email]);
        }

        $this->showForm = false;
        $this->notify($this->editingId ? 'Usuario actualizado.' : 'Usuario creado.');
    }

    /** Quita todos los roles del usuario en esta entidad (sigue existiendo en las demás). */
    public function removeFromOrganization(int $id): void
    {
        $this->authorize('usuarios.gestionar');
        $user = User::findOrFail($id);

        if ($user->is(auth()->user())) {
            $this->notify('No podés quitarte a vos mismo.', 'error');

            return;
        }

        $user->syncRoles([]);
        $this->notify("{$user->name} ya no tiene acceso a esta entidad.");
    }

    public function sendReset(int $id): void
    {
        $this->authorize('usuarios.gestionar');
        $user = User::findOrFail($id);
        Password::sendResetLink(['email' => $user->email]);
        $this->notify("Se envió un enlace de restablecimiento a {$user->email}.");
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'email', 'phone', 'password', 'is_active', 'is_super_admin', 'roles', 'sendInvite', 'facilityIds']);
        $this->resetValidation();
    }

    public function render()
    {
        // Personal de esta entidad: con algún rol aquí, o super administradores.
        $users = User::query()
            ->with('roles')
            ->where(fn ($q) => $q->whereHas('roles')->orWhere('is_super_admin', true))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'ilike', "%{$this->search}%")->orWhere('email', 'ilike', "%{$this->search}%")))
            ->orderByDesc('is_super_admin')
            ->orderBy('name')
            ->paginate(20);

        // Otras entidades donde cada usuario tiene rol (para mostrarlo en la lista).
        $otherOrgs = DB::table('model_has_roles')
            ->join('organizations', 'organizations.id', '=', 'model_has_roles.organization_id')
            ->where('model_type', (new User)->getMorphClass())
            ->whereIn('model_id', $users->pluck('id'))
            ->where('organization_id', '!=', Organization::currentId())
            ->select('model_id', 'organizations.name')
            ->distinct()
            ->get()
            ->groupBy('model_id')
            ->map(fn ($rows) => $rows->pluck('name')->implode(', '));

        return view('livewire.admin.users.index', [
            'users' => $users,
            'otherOrgs' => $otherOrgs,
            'availableRoles' => Role::orderBy('name')->get(),
            'availableFacilities' => Facility::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
