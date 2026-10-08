<?php

namespace App\Livewire\Admin\Organizations;

use App\Enums\FeeStatus;
use App\Enums\MemberStatus;
use App\Enums\PaymentStatus;
use App\Http\Middleware\ResolveOrganization;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Fee;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\OrganizationProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Alta y edición de entidades (clubes y gimnasios). Exclusivo del super administrador. */
#[Layout('layouts.admin')]
#[Title('Entidades')]
class Index extends Component
{
    use InteractsWithUi;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $slug = '';

    public string $type = 'club';

    public string $domain = '';

    public bool $is_active = true;

    public bool $is_default = false;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $org = Organization::findOrFail($id);
        $this->editingId = $org->id;
        $this->name = $org->name;
        $this->slug = $org->slug;
        $this->type = $org->type;
        $this->domain = (string) $org->domain;
        $this->is_active = $org->is_active;
        $this->is_default = $org->is_default;
        $this->showForm = true;
    }

    public function updatedName(): void
    {
        if (! $this->editingId) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function save(OrganizationProvisioner $provisioner): void
    {
        $this->authorize('plataforma');
        $this->domain = strtolower(trim($this->domain));

        $data = $this->validate([
            'name' => 'required|string|max:120',
            'slug' => ['required', 'alpha_dash', 'max:60', Rule::unique('organizations')->ignore($this->editingId)],
            'type' => ['required', Rule::in(array_keys(Organization::TYPES))],
            'domain' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9.-]+$/', Rule::unique('organizations')->ignore($this->editingId)],
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ], ['domain.regex' => 'Ingresá solo el dominio, sin http:// ni barras (ej.: miclub.com).'], ['slug' => 'identificador', 'domain' => 'dominio']);

        if ($this->editingId && ! $data['is_active'] && $this->editingId === Organization::currentId()) {
            $this->addError('is_active', 'No podés desactivar la entidad en la que estás trabajando. Cambiá de entidad primero.');

            return;
        }

        $org = DB::transaction(function () use ($data) {
            if ($data['is_default']) {
                Organization::query()->update(['is_default' => false]);
            }

            return Organization::updateOrCreate(['id' => $this->editingId], [...$data, 'domain' => $data['domain'] ?: null]);
        });

        if (! $this->editingId) {
            $provisioner->provision($org);
        }

        $this->showForm = false;
        $this->notify($this->editingId ? 'Entidad actualizada.' : "Entidad «{$org->name}» creada con su configuración, sitio web y datos iniciales.");
    }

    /** Pasa a administrar la entidad elegida. */
    public function manage(int $id)
    {
        $this->authorize('plataforma');
        session([ResolveOrganization::ADMIN_KEY => Organization::findOrFail($id)->id]);

        return $this->redirectRoute('admin.dashboard');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'slug', 'type', 'domain', 'is_active', 'is_default']);
        $this->resetValidation();
    }

    public function render()
    {
        $organizations = Organization::withTrashed()->orderByDesc('is_default')->orderBy('name')->get();

        // Indicadores por entidad (consultas agrupadas, sin el filtro de la entidad actual).
        $members = Member::acrossOrganizations()->where('status', MemberStatus::Active)
            ->selectRaw('organization_id, COUNT(*) as n')->groupBy('organization_id')->pluck('n', 'organization_id');
        $income = Payment::acrossOrganizations()->where('status', PaymentStatus::Confirmed)
            ->whereDate('payment_date', '>=', today()->startOfMonth())
            ->selectRaw('organization_id, SUM(amount) as total')->groupBy('organization_id')->pluck('total', 'organization_id');
        $debt = Fee::acrossOrganizations()->where('status', FeeStatus::Overdue)
            ->selectRaw('organization_id, SUM(amount + surcharge - paid_amount) as total')->groupBy('organization_id')->pluck('total', 'organization_id');
        $plans = Subscription::acrossOrganizations()->current()
            ->selectRaw('organization_id, COUNT(*) as n')->groupBy('organization_id')->pluck('n', 'organization_id');

        return view('livewire.admin.organizations.index', compact('organizations', 'members', 'income', 'debt', 'plans'));
    }
}
