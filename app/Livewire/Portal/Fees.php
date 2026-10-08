<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Portal\Concerns\ForCurrentMember;
use App\Models\Fee;
use App\Models\PaymentReceipt;
use App\Services\PaymentReceiptService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.portal')]
#[Title('Mi cuenta')]
class Fees extends Component
{
    use ForCurrentMember, InteractsWithUi, WithFileUploads;

    public string $tab = 'pendientes';

    // ---- Informar un pago (comprobante de transferencia) ----
    public bool $showReport = false;

    /** @var array<int, string> */
    public array $feeIds = [];

    public string $amount = '';

    public string $transferDate = '';

    public string $reference = '';

    public $file = null;

    public function openReport(?int $feeId = null): void
    {
        abort_unless(setting('payments.receipts_enabled', true), 404);

        $open = $this->reportableFees();
        $this->feeIds = ($feeId ? $open->where('id', $feeId) : $open)->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        $this->recalculate();
        $this->transferDate = today()->toDateString();
        $this->reset(['reference', 'file']);
        $this->resetValidation();
        $this->showReport = true;
    }

    public function updatedFeeIds(): void
    {
        $this->recalculate();
    }

    private function recalculate(): void
    {
        $this->amount = $this->reportableFees()->whereIn('id', array_map('intval', $this->feeIds))
            ->reduce(fn (string $carry, Fee $fee) => bcadd($carry, $fee->balance(), 2), '0');
    }

    public function submitReport(PaymentReceiptService $service): void
    {
        $this->validate([
            'feeIds' => 'required|array|min:1',
            'amount' => 'required|numeric|min:0.01',
            'transferDate' => 'required|date|before_or_equal:today',
            'reference' => 'nullable|string|max:100',
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,heic,heif,pdf|max:8192',
        ], [
            'file.required' => 'Adjuntá la foto o el PDF del comprobante.',
        ], ['feeIds' => 'cuotas', 'amount' => 'importe', 'transferDate' => 'fecha', 'file' => 'comprobante']);

        $path = $this->file->store('receipts/'.$this->member()->organization_id, 'public');

        $receipt = $this->attempt(fn () => $service->submit(
            $this->member(),
            array_map('intval', $this->feeIds),
            number_format((float) $this->amount, 2, '.', ''),
            Carbon::parse($this->transferDate),
            $path,
            $this->reference ?: null,
        ));

        if ($receipt) {
            $this->showReport = false;
            $this->tab = 'comprobantes';
            $this->notify($receipt->status->value === 'aprobado' ? '¡Pago acreditado! Gracias.' : 'Recibimos tu comprobante. Te avisamos cuando se acredite.');
        }
    }

    /** Cuotas abiertas sin un comprobante en revisión. */
    private function reportableFees()
    {
        return $this->member()->openFees()
            ->whereNull('instructor_id')
            ->whereDoesntHave('receipts', fn ($q) => $q->where('status', 'pendiente'))
            ->orderBy('due_date')
            ->get();
    }

    public function render()
    {
        $member = $this->member();

        return view('livewire.portal.fees', [
            'balance' => $member->balance(),
            'openFees' => $member->openFees()->with(['receipts' => fn ($q) => $q->where('status', 'pendiente')])->orderBy('due_date')->get(),
            'history' => $member->fees()->whereNotIn('status', ['pendiente', 'parcial', 'vencida'])->latest('due_date')->limit(36)->get(),
            'payments' => $member->payments()->latest('payment_date')->latest('id')->limit(24)->get(),
            'receipts' => PaymentReceipt::where('member_id', $member->id)->latest('id')->limit(20)->get(),
            'reportFees' => $this->showReport ? $this->reportableFees() : collect(),
            'receiptsEnabled' => (bool) setting('payments.receipts_enabled', true),
        ]);
    }
}
