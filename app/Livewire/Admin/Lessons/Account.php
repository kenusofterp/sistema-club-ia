<?php

namespace App\Livewire\Admin\Lessons;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Fee;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Cuenta corriente de los alumnos con un profesor, en todas las entidades donde trabaja:
 * cargos a su nombre (packs y clases sueltas), saldos y cobros. Los cobros quedan a nombre del profesor.
 */
#[Layout('layouts.admin')]
#[Title('Mis cobros')]
class Account extends Component
{
    use InteractsWithUi;

    #[Url(as: 'profesor')]
    public string $instructorId = '';

    #[Url(as: 'buscar')]
    public string $search = '';

    public ?int $expandedMemberId = null;

    // ---- Cobro ----
    public bool $showPayment = false;

    public ?int $payMemberId = null;

    /** @var array<int, string> */
    public array $feeIds = [];

    public string $amount = '';

    public string $method = 'efectivo';

    public string $paymentDate = '';

    public string $reference = '';

    public function mount(): void
    {
        abort_if($this->organizationIds() === [], 403);
        if ($this->instructorId === '' || ! $this->instructorOptions()->has((int) $this->instructorId)) {
            $this->instructorId = (string) auth()->id();
        }
    }

    public function updatedInstructorId(): void
    {
        if (! $this->instructorOptions()->has((int) $this->instructorId)) {
            $this->instructorId = (string) auth()->id();
        }
        $this->expandedMemberId = null;
    }

    public function toggle(int $memberId): void
    {
        $this->expandedMemberId = $this->expandedMemberId === $memberId ? null : $memberId;
    }

    public function openPayment(int $memberId): void
    {
        $fees = $this->openFeesQuery()->where('member_id', $memberId)->get();
        abort_if($fees->isEmpty(), 404);

        $this->resetValidation();
        $this->payMemberId = $memberId;
        $this->feeIds = $fees->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->amount = $fees->reduce(fn (string $carry, Fee $fee) => bcadd($carry, $fee->balance(), 2), '0');
        $this->method = PaymentMethod::Cash->value;
        $this->paymentDate = today()->toDateString();
        $this->reference = '';
        $this->showPayment = true;
    }

    public function updatedFeeIds(): void
    {
        $this->amount = $this->openFeesQuery()->whereIn('id', array_map('intval', $this->feeIds))->get()
            ->reduce(fn (string $carry, Fee $fee) => bcadd($carry, $fee->balance(), 2), '0');
    }

    public function pay(PaymentService $payments): void
    {
        $this->validate([
            'feeIds' => 'required|array|min:1',
            'amount' => 'required|numeric|min:0.01',
            'method' => ['required', Rule::in(PaymentMethod::values())],
            'paymentDate' => 'required|date|before_or_equal:today',
            'reference' => 'nullable|string|max:100',
        ], [], ['feeIds' => 'cargos', 'amount' => 'importe', 'method' => 'medio de pago', 'paymentDate' => 'fecha']);

        $member = Member::acrossOrganizations()->findOrFail($this->payMemberId);
        abort_unless($this->canCollectIn($member->organization_id), 403);

        $feeIds = $this->openFeesQuery()->where('member_id', $member->id)->whereIn('id', array_map('intval', $this->feeIds))->pluck('id')->all();

        $done = $this->attempt(fn () => Organization::runFor($member->organization_id, fn () => $payments->register(
            $member,
            number_format((float) $this->amount, 2, '.', ''),
            PaymentMethod::from($this->method),
            $feeIds,
            Carbon::parse($this->paymentDate),
            $this->reference ?: null,
            null,
            auth()->user(),
        )), 'Cobro registrado.');

        if ($done) {
            $this->showPayment = false;
        }
    }

    // ---- Alcance ----

    /** @return array<int, int> entidades donde puede ver cobros (propios o de todos) */
    private function organizationIds(): array
    {
        $user = auth()->user();

        return array_values(array_unique([...$user->organizationIdsWith('cobros.propios'), ...$user->organizationIdsWith('agenda.todas')]));
    }

    /** @return array<int, int> entidades visibles para el profesor elegido */
    private function scopeOrganizationIds(): array
    {
        $user = auth()->user();

        return (int) $this->instructorId === $user->id
            ? $this->organizationIds()
            : $user->organizationIdsWith('agenda.todas');
    }

    private function canCollectIn(int $organizationId): bool
    {
        $user = auth()->user();

        return $user->hasPermissionIn('agenda.todas', $organizationId)
            || ((int) $this->instructorId === $user->id && $user->hasPermissionIn('cobros.propios', $organizationId));
    }

    /** @return Collection<int, string> */
    private function instructorOptions(): Collection
    {
        $seeAll = auth()->user()->organizationIdsWith('agenda.todas');
        $others = $seeAll === [] ? collect() : User::query()
            ->whereHas('facilities', fn ($q) => $q->withoutGlobalScope('organization')->whereIn('facilities.organization_id', $seeAll))
            ->pluck('name', 'id');

        return $others->put(auth()->id(), auth()->user()->name)->sort();
    }

    private function openFeesQuery()
    {
        return Fee::acrossOrganizations()
            ->open()
            ->where('instructor_id', (int) $this->instructorId)
            ->whereIn('organization_id', $this->scopeOrganizationIds());
    }

    public function render()
    {
        $fees = $this->openFeesQuery()
            ->with(['member' => fn ($q) => $q->withoutGlobalScope('organization')->withTrashed(), 'organization'])
            ->when(mb_strlen(trim($this->search)) >= 2, fn ($q) => $q->whereHas('member', fn ($m) => $m->withoutGlobalScope('organization')->search($this->search)))
            ->orderBy('due_date')
            ->get();

        $accounts = $fees->groupBy('member_id')->map(fn (Collection $memberFees) => [
            'member' => $memberFees->first()->member,
            'organization' => $memberFees->first()->organization,
            'fees' => $memberFees,
            'balance' => $memberFees->reduce(fn (string $carry, Fee $fee) => bcadd($carry, $fee->balance(), 2), '0'),
            'overdue' => $memberFees->filter(fn (Fee $fee) => $fee->due_date->isPast() && ! $fee->due_date->isToday())->count(),
        ])->sortBy(fn ($a) => $a['member']->sortableName())->values();

        $payments = Payment::acrossOrganizations()
            ->where('instructor_id', (int) $this->instructorId)
            ->whereIn('organization_id', $this->scopeOrganizationIds())
            ->where('status', PaymentStatus::Confirmed)
            ->with(['member' => fn ($q) => $q->withoutGlobalScope('organization')->withTrashed(), 'organization'])
            ->latest('payment_date')->latest('id')
            ->limit(15)
            ->get();

        $collectedThisMonth = Payment::acrossOrganizations()
            ->where('instructor_id', (int) $this->instructorId)
            ->whereIn('organization_id', $this->scopeOrganizationIds())
            ->where('status', PaymentStatus::Confirmed)
            ->whereBetween('payment_date', [today()->startOfMonth(), today()])
            ->sum('amount');

        return view('livewire.admin.lessons.account', [
            'accounts' => $accounts,
            'payments' => $payments,
            'totalDue' => $accounts->reduce(fn (string $carry, $a) => bcadd($carry, $a['balance'], 2), '0'),
            'collectedThisMonth' => (string) $collectedThisMonth,
            'instructorOptions' => $this->instructorOptions(),
            'payFees' => $this->showPayment && $this->payMemberId ? $fees->where('member_id', $this->payMemberId)->values() : collect(),
            'methods' => PaymentMethod::options(),
        ]);
    }
}
