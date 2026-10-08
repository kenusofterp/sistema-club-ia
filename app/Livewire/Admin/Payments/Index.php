<?php

namespace App\Livewire\Admin\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Payment;
use App\Services\PaymentService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Pagos')]
class Index extends Component
{
    use InteractsWithUi, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $method = '';

    #[Url]
    public string $status = '';

    #[Url(as: 'desde')]
    public string $from = '';

    #[Url(as: 'hasta')]
    public string $to = '';

    public bool $showCancel = false;

    public ?int $cancelId = null;

    public string $cancelReason = '';

    public function mount(): void
    {
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function updated($property): void
    {
        $this->resetPage();
    }

    public function confirmCancel(int $id): void
    {
        $this->authorize('pagos.anular');
        $this->cancelId = $id;
        $this->cancelReason = '';
        $this->resetValidation();
        $this->showCancel = true;
    }

    public function cancel(PaymentService $payments): void
    {
        $this->authorize('pagos.anular');
        $this->validate(['cancelReason' => 'required|string|min:3|max:250'], [], ['cancelReason' => 'motivo']);

        $done = $this->attempt(
            fn () => $payments->cancel(Payment::findOrFail($this->cancelId), $this->cancelReason, auth()->user()),
            'Pago anulado. Los cargos volvieron a quedar pendientes.'
        );

        if ($done) {
            $this->showCancel = false;
        }
    }

    private function query()
    {
        return Payment::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('receipt_number', 'ilike', "%{$this->search}%")
                ->orWhereHas('member', fn ($m) => $m->search($this->search))))
            ->when($this->method, fn ($q) => $q->where('method', $this->method))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->from, fn ($q) => $q->whereDate('payment_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('payment_date', '<=', $this->to));
    }

    public function render()
    {
        return view('livewire.admin.payments.index', [
            'payments' => $this->query()->with(['member', 'receiver'])->latest('payment_date')->latest('id')->paginate(25),
            'total' => $this->query()->where('status', PaymentStatus::Confirmed)->sum('amount'),
            'byMethod' => $this->query()->where('status', PaymentStatus::Confirmed)->selectRaw('method, SUM(amount) as total')->groupBy('method')->pluck('total', 'method'),
            'methods' => PaymentMethod::options(),
            'statuses' => PaymentStatus::options(),
        ]);
    }
}
