<?php

namespace App\Livewire\Admin\Expenses;

use App\Enums\PaymentMethod;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Expense;
use App\Models\Facility;
use App\Models\Organization;
use App\Services\ExpenseService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Gastos')]
class Index extends Component
{
    use InteractsWithUi, WithFileUploads, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'categoria')]
    public string $categoryFilter = '';

    #[Url(as: 'desde')]
    public string $from = '';

    #[Url(as: 'hasta')]
    public string $to = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $expense_date = '';

    public string $category = '';

    public string $description = '';

    public string $supplier = '';

    public ?int $facility_id = null;

    public string $period = '';

    public string $amount = '';

    public string $method = 'efectivo';

    public string $reference = '';

    public $attachment = null;

    public ?string $currentAttachment = null;

    public function mount(): void
    {
        $this->from = $this->from ?: today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: today()->toDateString();
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'categoryFilter', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function create(?string $category = null, ?int $facilityId = null): void
    {
        $this->authorize('gastos.gestionar');
        $this->resetForm();

        if ($category) {
            $this->category = $category;
            $this->facility_id = $facilityId;
            $this->period = today()->format('Y-m');
            // Si ya se pagó antes, propone el mismo importe y medio.
            $last = $this->lastRent();
            if ($last) {
                $this->amount = (string) $last->amount;
                $this->method = $last->method->value;
                $this->supplier = (string) $last->supplier;
            }
        }

        $this->showForm = true;
    }

    /** Abre el formulario con los datos de un gasto anterior (ej. el alquiler del mes pasado). */
    public function repeat(int $id): void
    {
        $this->authorize('gastos.gestionar');
        $this->resetForm();
        $e = Expense::findOrFail($id);
        $this->fillFrom($e);
        $this->expense_date = today()->toDateString();
        $this->period = $e->period ? $e->period->copy()->addMonthNoOverflow()->format('Y-m') : '';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('gastos.gestionar');
        $this->resetForm();
        $e = Expense::findOrFail($id);
        $this->editingId = $e->id;
        $this->fillFrom($e);
        $this->expense_date = $e->expense_date->toDateString();
        $this->period = $e->period?->format('Y-m') ?? '';
        $this->currentAttachment = $e->attachmentUrl();
        $this->showForm = true;
    }

    public function save(ExpenseService $expenses): void
    {
        $this->authorize('gastos.gestionar');

        $data = $this->validate([
            'expense_date' => 'required|date',
            'category' => 'required|string|max:60',
            'description' => 'nullable|string|max:250',
            'supplier' => 'nullable|string|max:150',
            'facility_id' => ['nullable', 'integer', org_exists('facilities')],
            'period' => 'nullable|date_format:Y-m',
            'amount' => 'required|numeric|min:0.01|max:9999999999',
            'method' => 'required|string',
            'reference' => 'nullable|string|max:100',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], [], [
            'expense_date' => 'fecha', 'category' => 'categoría', 'description' => 'detalle', 'supplier' => 'a quién se pagó',
            'facility_id' => 'instalación', 'period' => 'mes', 'amount' => 'importe', 'method' => 'medio de pago',
            'reference' => 'referencia', 'attachment' => 'comprobante',
        ]);
        unset($data['attachment']);

        foreach (['description', 'supplier', 'reference'] as $key) {
            $data[$key] = trim($data[$key]) ?: null;
        }
        $data['period'] = $data['period'] ? $data['period'].'-01' : null;

        if ($this->attachment) {
            $data['attachment_path'] = $this->attachment->store('expenses/'.Organization::currentId(), 'public');
        }

        $editing = $this->editingId ? Expense::findOrFail($this->editingId) : null;
        $saved = $this->attempt(
            fn () => $expenses->save($editing, $data, auth()->user()),
            $editing ? 'Gasto actualizado.' : 'Gasto registrado.'
        );

        if ($saved) {
            $this->showForm = false;
        }
    }

    public function delete(int $id, ExpenseService $expenses): void
    {
        $this->authorize('gastos.gestionar');
        $expenses->delete(Expense::findOrFail($id));
        $this->notify('Gasto eliminado.');
    }

    private function fillFrom(Expense $e): void
    {
        $this->fill([
            'category' => $e->category, 'description' => (string) $e->description, 'supplier' => (string) $e->supplier,
            'facility_id' => $e->facility_id, 'amount' => (string) $e->amount, 'method' => $e->method->value,
            'reference' => (string) $e->reference,
        ]);
    }

    /** Último alquiler pagado de la instalación elegida en el formulario. */
    private function lastRent(): ?Expense
    {
        if ($this->category !== Expense::RENT || ! $this->facility_id) {
            return null;
        }

        return Expense::where('category', Expense::RENT)->where('facility_id', $this->facility_id)
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->latest('expense_date')->latest('id')->first();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'category', 'description', 'supplier', 'facility_id', 'period', 'amount', 'method', 'reference', 'attachment', 'currentAttachment']);
        $this->expense_date = today()->toDateString();
        $this->resetValidation();
    }

    private function query()
    {
        return Expense::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('description', 'ilike', "%{$this->search}%")
                ->orWhere('supplier', 'ilike', "%{$this->search}%")
                ->orWhere('reference', 'ilike', "%{$this->search}%")))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->when($this->from, fn ($q) => $q->whereDate('expense_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('expense_date', '<=', $this->to));
    }

    public function render(ExpenseService $expenses)
    {
        return view('livewire.admin.expenses.index', [
            'expenses' => $this->query()->with(['facility', 'creator'])->latest('expense_date')->latest('id')->paginate(25),
            'total' => (string) $this->query()->sum('amount'),
            'byCategory' => $this->query()->selectRaw('category, SUM(amount) as total')->groupBy('category')->orderByDesc('total')->pluck('total', 'category'),
            'rentStatus' => $expenses->rentStatus(),
            'categories' => Expense::categoryOptions(),
            'facilities' => Facility::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'methods' => PaymentMethod::manualOptions(),
            'lastRent' => $this->showForm ? $this->lastRent() : null,
            'currentMonth' => ucfirst(Carbon::today()->translatedFormat('F')),
        ]);
    }
}
