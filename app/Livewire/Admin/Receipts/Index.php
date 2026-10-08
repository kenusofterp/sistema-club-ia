<?php

namespace App\Livewire\Admin\Receipts;

use App\Enums\ReceiptStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\PaymentReceipt;
use App\Services\PaymentReceiptService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Comprobantes de transferencia informados por los socios: revisar, acreditar o rechazar. */
#[Layout('layouts.admin')]
#[Title('Comprobantes')]
class Index extends Component
{
    use InteractsWithUi, WithPagination;

    #[Url(as: 'estado')]
    public string $status = 'pendiente';

    public ?int $viewingId = null;

    public bool $showView = false;

    public string $rejectReason = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function view(int $id): void
    {
        $this->viewingId = PaymentReceipt::findOrFail($id)->id;
        $this->rejectReason = '';
        $this->showView = true;
        $this->resetValidation();
    }

    public function approve(PaymentReceiptService $service): void
    {
        $this->authorize('comprobantes.revisar');
        $receipt = PaymentReceipt::findOrFail($this->viewingId);

        if ($this->attempt(fn () => $service->approve($receipt, auth()->user()), 'Pago acreditado y avisado al socio.')) {
            $this->showView = false;
        }
    }

    public function reject(PaymentReceiptService $service): void
    {
        $this->authorize('comprobantes.revisar');
        $this->validate(['rejectReason' => 'required|string|max:250'], [], ['rejectReason' => 'motivo']);
        $receipt = PaymentReceipt::findOrFail($this->viewingId);

        if ($this->attempt(fn () => $service->reject($receipt, $this->rejectReason, auth()->user()), 'Comprobante rechazado; se le avisó al socio.')) {
            $this->showView = false;
        }
    }

    public function render()
    {
        return view('livewire.admin.receipts.index', [
            'receipts' => PaymentReceipt::with(['member', 'fees', 'reviewer'])
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
                ->latest('id')
                ->paginate(20),
            'statuses' => ReceiptStatus::options(),
            'pendingCount' => PaymentReceipt::where('status', ReceiptStatus::Pending)->count(),
            'viewing' => $this->showView && $this->viewingId ? PaymentReceipt::with(['member', 'fees', 'payment', 'reviewer'])->find($this->viewingId) : null,
            'autoApprove' => (bool) setting('payments.receipts_auto_approve', false),
        ]);
    }
}
