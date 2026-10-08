<?php

namespace App\Livewire\Admin\Payments;

use App\Enums\PaymentMethod;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Fee;
use App\Models\Member;
use App\Services\PaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Registrar pago')]
class Create extends Component
{
    use InteractsWithUi;

    #[Url(as: 'socio')]
    public ?int $memberId = null;

    public string $memberSearch = '';

    /** @var array<int, string> ids de cargos seleccionados */
    public array $selected = [];

    public string $amount = '';

    public string $method = 'efectivo';

    public string $payment_date = '';

    public string $reference = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->payment_date = today()->toDateString();

        if ($this->memberId) {
            $this->selectMember($this->memberId);
        }
    }

    public function updatedMemberSearch(): void
    {
        $this->memberId = null;
        $this->selected = [];
        $this->amount = '';
    }

    public function selectMember(int $id): void
    {
        $member = Member::find($id);
        if (! $member) {
            $this->memberId = null;

            return;
        }

        $this->memberId = $member->id;
        $this->memberSearch = $member->fullName();
        $this->selected = $member->openFees()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->syncAmount();
    }

    public function updatedSelected(): void
    {
        $this->syncAmount();
    }

    private function syncAmount(): void
    {
        $total = Fee::whereIn('id', $this->selected)->where('member_id', $this->memberId)->get()
            ->reduce(fn ($carry, Fee $fee) => bcadd($carry, $fee->balance(), 2), '0');
        $this->amount = $total;
    }

    public function save(PaymentService $payments)
    {
        $this->authorize('pagos.registrar');

        $this->validate([
            'memberId' => 'required|exists:members,id',
            'selected' => 'required|array|min:1',
            'amount' => 'required|numeric|min:0.01',
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment_date' => 'required|date|before_or_equal:today',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:250',
        ], ['selected.required' => 'Seleccioná al menos un cargo.'], ['memberId' => 'socio', 'amount' => 'importe', 'payment_date' => 'fecha']);

        $payment = $this->attempt(fn () => $payments->register(
            Member::findOrFail($this->memberId),
            number_format((float) $this->amount, 2, '.', ''),
            PaymentMethod::from($this->method),
            array_map('intval', $this->selected),
            Carbon::parse($this->payment_date),
            $this->reference ?: null,
            $this->notes ?: null,
            auth()->user(),
        ));

        if (! $payment) {
            return null;
        }

        session()->flash('success', "Pago registrado. Recibo {$payment->receipt_number}.");
        session()->flash('receipt_url', route('admin.payments.receipt', $payment));

        return $this->redirectRoute('admin.members.show', $this->memberId, navigate: true);
    }

    public function render()
    {
        $member = $this->memberId ? Member::with('category')->find($this->memberId) : null;

        return view('livewire.admin.payments.create', [
            'member' => $member,
            'fees' => $member ? $member->openFees()->orderBy('due_date')->orderBy('id')->get() : collect(),
            'results' => ! $this->memberId && strlen($this->memberSearch) >= 2
                ? Member::search($this->memberSearch)->whereIn('status', ['activo', 'suspendido', 'baja'])->limit(8)->get()
                : collect(),
            'methods' => PaymentMethod::options(),
        ]);
    }
}
