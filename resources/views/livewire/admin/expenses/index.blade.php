<div>
    <x-page-header title="Gastos" subtitle="Alquiler del salón, servicios, sueldos y todo lo que se paga">
        <x-slot:actions>
            @can('gastos.gestionar')
                <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Registrar gasto</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Alquileres: último pago de cada salón y si el mes actual ya está pago --}}
    @if ($rentStatus->isNotEmpty())
        <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($rentStatus as $rent)
                <div class="card flex items-start justify-between gap-3 p-5" wire:key="rent-{{ $rent['facility']->id }}">
                    <div class="min-w-0">
                        <p class="text-sm text-slate-500">Alquiler · {{ $rent['facility']->name }}</p>
                        <p class="mt-0.5 font-display text-xl font-bold tabular-nums text-slate-900">{{ money($rent['last']->amount) }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Último pago: {{ $rent['last']->expense_date->format('d/m/Y') }}@if ($rent['last']->period) ({{ mb_strtolower($rent['last']->periodLabel()) }})@endif
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-2">
                        @if ($rent['paidThisMonth'])
                            <x-badge color="green">{{ $currentMonth }} pago</x-badge>
                        @else
                            <x-badge color="yellow">{{ $currentMonth }} sin pagar</x-badge>
                            @can('gastos.gestionar')
                                <button type="button" wire:click="create('{{ \App\Models\Expense::RENT }}', {{ $rent['facility']->id }})" class="btn-secondary btn-sm">Registrar pago</button>
                            @endcan
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Total gastado (período)" :value="money($total)" icon="banknotes" tone="red" />
        @foreach ($byCategory->take(3) as $name => $amount)
            <x-stat-card :label="$name" :value="money($amount)" icon="tag" tone="amber" />
        @endforeach
    </div>

    <div class="card">
        <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-4">
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Detalle, a quién o referencia…" class="form-input">
            <x-date-input wire:model.live="from" title="Desde" />
            <x-date-input wire:model.live="to" title="Hasta" />
            <select wire:model.live="categoryFilter" class="form-input">
                <option value="">Todas las categorías</option>
                @foreach ($categories as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Categoría</th><th>Detalle</th><th>Medio</th><th class="text-right">Importe</th><th class="hidden lg:table-cell">Registró</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($expenses as $expense)
                        <tr wire:key="e-{{ $expense->id }}">
                            <td class="whitespace-nowrap">{{ $expense->expense_date->format('d/m/Y') }}</td>
                            <td>
                                {{ $expense->category }}
                                @if ($expense->periodLabel())<span class="block text-xs text-slate-400">{{ $expense->periodLabel() }}</span>@endif
                            </td>
                            <td>
                                {{ $expense->description ?? ($expense->facility?->name ?? '—') }}
                                @if ($expense->supplier || ($expense->facility && $expense->description))
                                    <span class="block text-xs text-slate-400">{{ collect([$expense->description ? $expense->facility?->name : null, $expense->supplier])->filter()->join(' · ') }}</span>
                                @endif
                            </td>
                            <td>{{ $expense->method->label() }}@if ($expense->reference)<span class="block text-xs text-slate-400">{{ $expense->reference }}</span>@endif</td>
                            <td class="text-right font-medium tabular-nums">{{ money($expense->amount) }}</td>
                            <td class="hidden lg:table-cell">{{ $expense->creator?->name ?? '—' }}</td>
                            <td class="text-right whitespace-nowrap">
                                @if ($expense->attachmentUrl())
                                    <a href="{{ $expense->attachmentUrl() }}" target="_blank" class="btn-ghost btn-sm" title="Ver comprobante"><x-icon name="document" class="size-4" /></a>
                                @endif
                                @can('gastos.gestionar')
                                    <button type="button" wire:click="repeat({{ $expense->id }})" class="btn-ghost btn-sm" title="Repetir (ej. el alquiler del mes siguiente)"><x-icon name="refresh" class="size-4" /></button>
                                    <button type="button" wire:click="edit({{ $expense->id }})" class="btn-ghost btn-sm" title="Editar"><x-icon name="pencil" class="size-4" /></button>
                                    <button type="button" wire:click="delete({{ $expense->id }})" wire:confirm="¿Eliminar este gasto?" class="btn-ghost btn-sm text-red-600" title="Eliminar"><x-icon name="trash" class="size-4" /></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="banknotes" title="Sin gastos en el período" description="Registrá el alquiler del salón y los demás pagos para ver el resultado del mes en el tablero." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($expenses->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $expenses->links() }}</div>
        @endif
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar gasto' : 'Registrar gasto'">
        <form wire:submit="save" id="expense-form" class="grid gap-4 sm:grid-cols-2">
            <x-field label="Categoría" for="category" error="category" required help="Elegí una o escribí otra.">
                <input id="category" wire:model.live.debounce.300ms="category" list="expense-categories" autocomplete="off" class="form-input">
                <datalist id="expense-categories">
                    @foreach ($categories as $name)
                        <option value="{{ $name }}"></option>
                    @endforeach
                </datalist>
            </x-field>
            <x-field label="Instalación" for="facility_id" error="facility_id" help="Opcional. Para el alquiler, elegí el salón.">
                <select id="facility_id" wire:model.live="facility_id" class="form-input">
                    <option value="">— Ninguna —</option>
                    @foreach ($facilities as $facility)
                        <option value="{{ $facility->id }}">{{ $facility->name }}</option>
                    @endforeach
                </select>
            </x-field>

            @if ($lastRent)
                <p class="rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-800 sm:col-span-2">
                    Último alquiler de este salón: <strong>{{ money($lastRent->amount) }}</strong> el {{ $lastRent->expense_date->format('d/m/Y') }}@if ($lastRent->period) ({{ mb_strtolower($lastRent->periodLabel()) }})@endif.
                </p>
            @endif

            <x-field label="Fecha de pago" for="expense_date" error="expense_date" required>
                <x-date-input id="expense_date" wire:model="expense_date" :max="today()->toDateString()" />
            </x-field>
            <x-field label="Mes al que corresponde" for="period" error="period" help="Opcional. Ej.: el alquiler de octubre.">
                <input id="period" type="month" wire:model="period" class="form-input">
            </x-field>
            <x-field label="Importe" for="amount" error="amount" required>
                <input id="amount" type="number" step="0.01" min="0.01" wire:model="amount" class="form-input" inputmode="decimal">
            </x-field>
            <x-field label="Medio de pago" for="method" error="method" required>
                <select id="method" wire:model="method" class="form-input">
                    @foreach ($methods as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Detalle" for="description" error="description" class="sm:col-span-2">
                <input id="description" wire:model="description" class="form-input" placeholder="Ej.: alquiler del salón, factura de luz…">
            </x-field>
            <x-field label="A quién se pagó" for="supplier" error="supplier">
                <input id="supplier" wire:model="supplier" class="form-input" placeholder="Ej.: dueño del salón, Edenor…">
            </x-field>
            <x-field label="Referencia" for="reference" error="reference" help="N° de transferencia, factura o recibo.">
                <input id="reference" wire:model="reference" class="form-input">
            </x-field>
            <x-field label="Comprobante (foto o PDF)" for="attachment" error="attachment" class="sm:col-span-2">
                <input id="attachment" type="file" wire:model="attachment" accept="image/*,application/pdf" class="form-input">
                @if ($currentAttachment && ! $attachment)
                    <a href="{{ $currentAttachment }}" target="_blank" class="mt-1 inline-block text-sm font-medium text-brand-700">Ver el comprobante cargado</a>
                @endif
            </x-field>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="expense-form" class="btn-primary" wire:loading.attr="disabled" wire:target="attachment,save">Guardar</button>
        </x-slot:footer>
    </x-modal>
</div>
