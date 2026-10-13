<?php

namespace App\Livewire\Admin\Lessons;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Fee;
use App\Models\Member;
use App\Services\CashCollectionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** El profesor cobra en efectivo las cuotas de los alumnos de sus actividades (pensado para el celular). */
#[Layout('layouts.admin')]
#[Title('Cobrar')]
class Collect extends Component
{
    use InteractsWithUi;

    public string $search = '';

    #[Url(as: 'alumno')]
    public ?int $memberId = null;

    /** @var array<int, string> */
    public array $feeIds = [];

    public string $amount = '';

    public function mount(): void
    {
        abort_unless(setting('payments.instructors_collect_cash', true), 404);
        if ($this->memberId) {
            $this->select($this->memberId);
        }
    }

    public function select(int $memberId): void
    {
        $member = $this->students()->findOrFail($memberId);
        $this->memberId = $member->id;
        $this->search = '';
        $this->feeIds = $this->openFees($member)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->recalculate();
    }

    public function clear(): void
    {
        $this->reset(['memberId', 'feeIds', 'amount']);
    }

    public function updatedFeeIds(): void
    {
        $this->recalculate();
    }

    private function recalculate(): void
    {
        $member = $this->memberId ? $this->students()->find($this->memberId) : null;
        $this->amount = $member
            ? $this->openFees($member)->whereIn('id', array_map('intval', $this->feeIds))->reduce(fn (string $c, Fee $f) => bcadd($c, $f->balance(), 2), '0')
            : '';
    }

    public function collect(CashCollectionService $cash): void
    {
        $this->validate([
            'memberId' => 'required|integer',
            'feeIds' => 'required|array|min:1',
            'amount' => 'required|numeric|min:0.01',
        ], [], ['memberId' => 'alumno', 'feeIds' => 'cuotas', 'amount' => 'importe']);

        $member = $this->students()->findOrFail($this->memberId);

        $payment = $this->attempt(fn () => $cash->collect(
            auth()->user(),
            $member,
            array_map('intval', $this->feeIds),
            number_format((float) $this->amount, 2, '.', ''),
        ));

        if ($payment) {
            $this->notify('Cobro registrado: recibo '.$payment->receipt_number.' por '.money($payment->amount).'.');
            $this->clear();
        }
    }

    /** Paga las cuotas tildadas con el saldo a favor del alumno (hasta lo disponible). */
    public function payWithCredit(CashCollectionService $cash): void
    {
        $this->validate(['memberId' => 'required|integer', 'feeIds' => 'required|array|min:1'], [], ['feeIds' => 'cuotas']);

        $member = $this->students()->findOrFail($this->memberId);
        $total = $this->openFees($member)->whereIn('id', array_map('intval', $this->feeIds))->reduce(fn (string $c, Fee $f) => bcadd($c, $f->balance(), 2), '0');
        $credit = $member->creditBalance();
        $amount = bccomp($total, $credit, 2) > 0 ? $credit : $total;

        $payment = $this->attempt(fn () => $cash->payWithCredit(auth()->user(), $member, array_map('intval', $this->feeIds), $amount));

        if ($payment) {
            $this->notify('Pagado con saldo a favor: recibo '.$payment->receipt_number.' por '.money($payment->amount).'.');
            $this->select($member->id);
        }
    }

    private function students()
    {
        return app(CashCollectionService::class)->studentsQuery(auth()->user());
    }

    private function openFees(Member $member)
    {
        return $member->openFees()->whereNull('instructor_id')->orderBy('due_date')->get();
    }

    public function render()
    {
        $member = $this->memberId ? $this->students()->with('activities')->find($this->memberId) : null;

        return view('livewire.admin.lessons.collect', [
            'member' => $member,
            'fees' => $member ? $this->openFees($member) : collect(),
            'credit' => $member?->creditBalance() ?? '0',
            'results' => ! $member && mb_strlen(trim($this->search)) >= 2
                ? $this->students()->search($this->search)->orderBy('last_name')->limit(10)->get()
                : collect(),
            'withDebt' => ! $member && mb_strlen(trim($this->search)) < 2
                ? $this->students()->whereHas('openFees', fn ($q) => $q->whereNull('instructor_id'))->orderBy('last_name')->limit(30)->get()
                : collect(),
        ]);
    }
}
