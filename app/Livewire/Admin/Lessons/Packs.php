<?php

namespace App\Livewire\Admin\Lessons;

use App\Enums\PlanAccessType;
use App\Enums\SubscriptionStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\SearchesPeople;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MemberService;
use App\Services\SubscriptionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Packs de clases de un profesor (planes con instructor_id) y su venta a los alumnos.
 * Cada pack es de una entidad: se consume con las clases que el profesor da en esa entidad.
 */
#[Layout('layouts.admin')]
#[Title('Packs de clases')]
class Packs extends Component
{
    use InteractsWithUi, SearchesPeople;

    #[Url(as: 'club')]
    public string $organizationId = '';

    #[Url(as: 'profesor')]
    public string $instructorId = '';

    // ---- Formulario de pack ----
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $price = '';

    public ?int $visit_limit = 4;

    public string $visit_period = 'mes';

    public int $duration_value = 1;

    public string $duration_unit = 'meses';

    public bool $is_active = true;

    // ---- Venta ----
    public bool $showSell = false;

    public ?int $sellPlanId = null;

    public string $sellSearch = '';

    public ?int $sellMemberId = null;

    public string $sellMemberName = '';

    public string $sellStart = '';

    public bool $sellAutoRenew = false;

    /** Precio propio de la clase suelta del profesor (vacío = el de cada entidad). */
    public string $ownPrice = '';

    public function mount(): void
    {
        $options = $this->organizationOptions();
        abort_if($options->isEmpty(), 403);

        if (! $options->has((int) $this->organizationId)) {
            $this->organizationId = (string) ($options->has(Organization::currentId()) ? Organization::currentId() : $options->keys()->first());
        }
        $this->normalizeInstructor();
        $this->ownPrice = (string) auth()->user()->preference('lessons.single_price', '');
    }

    public function saveOwnPrice(): void
    {
        $this->validate(['ownPrice' => 'nullable|numeric|min:0|max:99999999'], [], ['ownPrice' => 'precio']);
        auth()->user()->setPreference('lessons.single_price', $this->ownPrice === '' ? null : number_format((float) $this->ownPrice, 2, '.', ''));
        $this->notify('Precio de la clase suelta guardado.');
    }

    public function updatedOrganizationId(): void
    {
        if (! $this->organizationOptions()->has((int) $this->organizationId)) {
            $this->organizationId = (string) $this->organizationOptions()->keys()->first();
        }
        $this->normalizeInstructor();
    }

    public function updatedInstructorId(): void
    {
        $this->normalizeInstructor();
    }

    // ---- Packs ----

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'price', 'visit_limit', 'visit_period', 'duration_value', 'duration_unit', 'is_active']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $plan = $this->findPack($id);
        $this->editingId = $plan->id;
        $this->name = $plan->name;
        $this->price = (string) $plan->price;
        $this->visit_limit = $plan->visit_limit;
        $this->visit_period = $plan->visit_period;
        $this->duration_value = $plan->duration_value;
        $this->duration_unit = $plan->duration_unit;
        $this->is_active = $plan->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0|max:99999999',
            'visit_limit' => 'nullable|integer|min:1|max:500',
            'visit_period' => ['required', Rule::in(array_keys(Plan::VISIT_PERIODS))],
            'duration_value' => 'required|integer|min:1|max:36',
            'duration_unit' => ['required', Rule::in(array_keys(Plan::DURATION_UNITS))],
            'is_active' => 'boolean',
        ], [], ['visit_limit' => 'clases', 'visit_period' => 'período', 'duration_value' => 'duración']);

        Organization::runFor((int) $this->organizationId, function () use ($data) {
            $data['price'] = number_format((float) $data['price'], 2, '.', '');
            if ($this->editingId) {
                $this->findPack($this->editingId)->update($data);
            } else {
                Plan::create([
                    ...$data,
                    'instructor_id' => (int) $this->instructorId,
                    'access_type' => PlanAccessType::Free,
                    'is_public' => false,
                ]);
            }
        });

        $this->showForm = false;
        $this->notify($this->editingId ? 'Pack actualizado.' : 'Pack creado.');
    }

    // ---- Venta del pack a un alumno ----

    public function sell(int $planId): void
    {
        $plan = $this->findPack($planId);
        abort_unless($plan->is_active, 404);
        $this->reset(['sellSearch', 'sellMemberId', 'sellMemberName']);
        $this->resetValidation();
        $this->sellPlanId = $plan->id;
        $this->sellStart = today()->toDateString();
        $this->sellAutoRenew = $plan->visit_period !== 'plan';
        $this->showSell = true;
    }

    public function selectStudent(int $memberId): void
    {
        $member = Member::acrossOrganizations()->findOrFail($memberId);
        $this->sellMemberId = $member->id;
        $this->sellMemberName = $member->fullName();
        $this->sellSearch = '';
    }

    public function confirmSale(SubscriptionService $subscriptions, MemberService $members): void
    {
        $this->validate([
            'sellMemberId' => 'required|integer',
            'sellStart' => 'required|date|after_or_equal:today',
        ], [], ['sellMemberId' => 'alumno', 'sellStart' => 'inicio']);

        $plan = $this->findPack($this->sellPlanId);
        $selected = Member::acrossOrganizations()->findOrFail($this->sellMemberId);

        $done = $this->attempt(fn () => Organization::runFor($plan->organization_id, function () use ($subscriptions, $members, $plan, $selected) {
            $member = $selected->organization_id === $plan->organization_id
                ? $selected
                : $members->joinOrganization(Person::findOrFail($selected->person_id));

            return $subscriptions->subscribe($member, $plan, Carbon::parse($this->sellStart), $this->sellAutoRenew, auth()->user());
        }), 'Pack asignado. El cargo quedó en la cuenta del alumno a nombre del profesor.');

        if ($done) {
            $this->showSell = false;
        }
    }

    public function cancelSubscription(int $id, SubscriptionService $subscriptions): void
    {
        $subscription = Subscription::acrossOrganizations()
            ->whereIn('plan_id', $this->packsQuery()->select('id'))
            ->findOrFail($id);

        $this->attempt(fn () => Organization::runFor($subscription->organization_id, fn () => $subscriptions->cancel($subscription, 'Cancelado por el profesor')), 'Pack cancelado.');
    }

    // ---- Alcance ----

    /** @return Collection<int, string> entidades donde puede gestionar packs */
    private function organizationOptions(): Collection
    {
        $user = auth()->user();
        $ids = array_unique([...$user->organizationIdsWith('agenda.gestionar'), ...$user->organizationIdsWith('agenda.todas')]);

        return Organization::query()->whereIn('id', $ids)->orderBy('name')->pluck('name', 'id');
    }

    private function seesAll(): bool
    {
        return auth()->user()->hasPermissionIn('agenda.todas', (int) $this->organizationId);
    }

    private function normalizeInstructor(): void
    {
        if (! $this->seesAll() || $this->instructorId === '' || ! $this->instructorOptions()->has((int) $this->instructorId)) {
            $this->instructorId = (string) auth()->id();
        }
    }

    /** @return Collection<int, string> profesores con sedes en la entidad (solo para quien ve todas) */
    private function instructorOptions(): Collection
    {
        if (! $this->seesAll()) {
            return collect();
        }

        return User::query()
            ->whereHas('facilities', fn ($q) => $q->withoutGlobalScope('organization')->where('facilities.organization_id', (int) $this->organizationId))
            ->orWhere('id', auth()->id())
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    private function packsQuery()
    {
        return Plan::acrossOrganizations()
            ->where('organization_id', (int) $this->organizationId)
            ->where('instructor_id', (int) $this->instructorId);
    }

    private function findPack(?int $id): Plan
    {
        return $this->packsQuery()->findOrFail($id);
    }

    public function render()
    {
        $packs = $this->packsQuery()->orderByDesc('is_active')->orderBy('name')->get();

        $subscriptions = Subscription::acrossOrganizations()
            ->whereIn('plan_id', $packs->pluck('id'))
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Pending])
            ->whereDate('end_date', '>=', today())
            ->with([
                'member' => fn ($q) => $q->withoutGlobalScope('organization'),
                'plan' => fn ($q) => $q->withoutGlobalScope('organization'),
            ])
            ->orderBy('end_date')
            ->get();

        return view('livewire.admin.lessons.packs', [
            'packs' => $packs,
            'subscriptions' => $subscriptions,
            'organizationOptions' => $this->organizationOptions(),
            'instructorOptions' => $this->instructorOptions(),
            'sellPlan' => $this->showSell && $this->sellPlanId ? $packs->firstWhere('id', $this->sellPlanId) : null,
            'sellResults' => $this->showSell && ! $this->sellMemberId ? $this->searchPeople($this->sellSearch, (int) $this->organizationId) : collect(),
        ]);
    }
}
