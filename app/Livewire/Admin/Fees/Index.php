<?php

namespace App\Livewire\Admin\Fees;

use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Fee;
use App\Models\Member;
use App\Services\FeeService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Cuotas y cargos')]
class Index extends Component
{
    use InteractsWithUi, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $type = '';

    #[Url(as: 'periodo')]
    public string $period = '';

    public bool $showGenerate = false;

    public string $generatePeriod = '';

    public bool $showCancel = false;

    public ?int $cancelId = null;

    public string $cancelReason = '';

    public function mount(): void
    {
        $this->generatePeriod = today()->format('Y-m');
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'type', 'period'], true)) {
            $this->resetPage();
        }
    }

    /** Generación manual del período (además del proceso automático programado). */
    public function generate(FeeService $fees): void
    {
        $this->authorize('cuotas.gestionar');
        $this->validate(['generatePeriod' => 'required|date_format:Y-m'], [], ['generatePeriod' => 'período']);

        $period = Carbon::createFromFormat('Y-m', $this->generatePeriod)->startOfMonth();
        if ($period->gt(today()->addMonth()->startOfMonth())) {
            $this->addError('generatePeriod', 'Solo se puede generar hasta el mes próximo.');

            return;
        }

        $created = 0;
        $members = 0;
        Member::active()->with('category')->chunkById(200, function ($chunk) use ($fees, $period, &$created, &$members) {
            foreach ($chunk as $member) {
                $created += $fees->generateForMember($member, $period);
                $members++;
            }
        });

        activity('fees')->event('generated')->withProperties(['period' => $period->format('Y-m'), 'members' => $members, 'created' => $created])
            ->log('Generación manual de cuotas '.$period->format('m/Y'));

        $this->showGenerate = false;
        $this->notify("Se generaron {$created} cargos para {$members} socios activos ({$period->format('m/Y')}).");
    }

    public function processOverdue(FeeService $fees): void
    {
        $this->authorize('cuotas.gestionar');
        $count = $fees->markOverdue();
        $this->notify($count ? "{$count} cargos marcados como vencidos." : 'No hay cargos para vencer.', $count ? 'success' : 'info');
    }

    public function confirmCancel(int $id): void
    {
        $this->authorize('cuotas.gestionar');
        $this->cancelId = $id;
        $this->cancelReason = '';
        $this->resetValidation();
        $this->showCancel = true;
    }

    public function cancel(FeeService $fees): void
    {
        $this->authorize('cuotas.gestionar');
        $this->validate(['cancelReason' => 'required|string|min:3|max:250'], [], ['cancelReason' => 'motivo']);

        if ($this->attempt(fn () => $fees->cancel(Fee::findOrFail($this->cancelId), $this->cancelReason), 'Cargo anulado.')) {
            $this->showCancel = false;
        }
    }

    private function query()
    {
        return Fee::query()
            ->when($this->search, fn ($q) => $q->whereHas('member', fn ($m) => $m->search($this->search)))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->period, fn ($q) => $q->whereDate('period', Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()));
    }

    public function render()
    {
        $totals = $this->query()
            ->where('status', '!=', FeeStatus::Cancelled)
            ->selectRaw('COALESCE(SUM(amount + surcharge), 0) as billed, COALESCE(SUM(paid_amount), 0) as paid')
            ->first();

        return view('livewire.admin.fees.index', [
            'fees' => $this->query()->with('member')->orderByDesc('due_date')->orderByDesc('id')->paginate(25),
            'totals' => $totals,
            'statuses' => FeeStatus::options(),
            'types' => FeeType::options(),
        ]);
    }
}
