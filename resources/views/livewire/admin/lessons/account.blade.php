<div>
    <x-page-header title="Mis cobros" subtitle="Cuenta corriente de los alumnos: packs y clases sueltas a nombre del profesor">
        <x-slot:breadcrumb><a href="{{ route('admin.lessons') }}" wire:navigate class="hover:text-slate-700">Agenda de clases</a> ›</x-slot:breadcrumb>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Saldo a cobrar" :value="money($totalDue)" icon="banknotes" />
        <x-stat-card label="Alumnos con saldo" :value="$accounts->count()" icon="users" />
        <x-stat-card label="Cobrado este mes" :value="money($collectedThisMonth)" icon="check-circle" />
    </div>

    <div class="card mb-4 flex flex-wrap items-center gap-3 p-4">
        <div class="relative min-w-0 flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
            <input wire:model.live.debounce.300ms="search" class="form-input pl-9" placeholder="Buscar alumno…" aria-label="Buscar alumno">
        </div>
        @if ($instructorOptions->count() > 1)
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <span>Profesor</span>
                <select wire:model.live="instructorId" class="form-input w-auto py-1.5">
                    @foreach ($instructorOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    <div class="card mb-8">
        <ul class="divide-y divide-slate-100">
            @forelse ($accounts as $account)
                <li wire:key="acc-{{ $account['member']->id }}">
                    <div class="flex flex-wrap items-center gap-3 px-5 py-3">
                        <button type="button" wire:click="toggle({{ $account['member']->id }})" class="flex min-w-0 flex-1 items-center gap-3 text-left">
                            <x-icon name="chevron-right" @class(['size-4 shrink-0 text-slate-400 transition', 'rotate-90' => $expandedMemberId === $account['member']->id]) />
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-slate-800">{{ $account['member']->sortableName() }}</span>
                                <span class="block text-xs text-slate-500">{{ $account['organization']->name }} · {{ $account['fees']->count() }} {{ $account['fees']->count() === 1 ? 'cargo' : 'cargos' }}@if ($account['overdue']) · <span class="text-red-600">{{ $account['overdue'] }} vencido(s)</span>@endif</span>
                            </span>
                        </button>
                        <span class="font-semibold tabular-nums text-slate-900">{{ money($account['balance']) }}</span>
                        <button type="button" wire:click="openPayment({{ $account['member']->id }})" class="btn-primary btn-sm">Cobrar</button>
                    </div>
                    @if ($expandedMemberId === $account['member']->id)
                        <div class="overflow-x-auto bg-slate-50/60 px-5 pb-3">
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($account['fees'] as $fee)
                                        <tr>
                                            <td class="py-2 pr-3 text-slate-700">{{ $fee->concept }}</td>
                                            <td class="py-2 pr-3 text-slate-500">Vence {{ $fee->due_date->format('d/m/Y') }}</td>
                                            <td class="py-2 pr-3"><x-badge :status="$fee->status" /></td>
                                            <td class="py-2 text-right tabular-nums text-slate-900">{{ money($fee->balance()) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </li>
            @empty
                <li><x-empty-state icon="check-circle" title="No hay saldos pendientes" description="Los packs vendidos y las clases sueltas impagas aparecen acá." /></li>
            @endforelse
        </ul>
    </div>

    <div class="card">
        <div class="border-b border-slate-100 px-5 py-3">
            <h2 class="font-semibold text-slate-900">Últimos cobros</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-2 font-medium">Fecha</th>
                        <th class="px-5 py-2 font-medium">Recibo</th>
                        <th class="px-5 py-2 font-medium">Alumno</th>
                        <th class="px-5 py-2 font-medium">Club</th>
                        <th class="px-5 py-2 font-medium">Medio</th>
                        <th class="px-5 py-2 text-right font-medium">Importe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $payment)
                        <tr wire:key="pay-{{ $payment->id }}">
                            <td class="px-5 py-2 text-slate-600">{{ $payment->payment_date->format('d/m/Y') }}</td>
                            <td class="px-5 py-2 text-slate-600">{{ $payment->receipt_number }}</td>
                            <td class="px-5 py-2 font-medium text-slate-800">{{ $payment->member->fullName() }}</td>
                            <td class="px-5 py-2 text-slate-600">{{ $payment->organization->name }}</td>
                            <td class="px-5 py-2 text-slate-600">{{ $payment->method->label() }}</td>
                            <td class="px-5 py-2 text-right tabular-nums text-slate-900">{{ money($payment->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-6 text-center text-slate-500">Todavía no hay cobros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-modal wire:model="showPayment" title="Registrar cobro" max-width="max-w-lg">
        <div class="grid gap-4">
            <x-field label="Cargos" for="feeIds" error="feeIds">
                <ul class="divide-y divide-slate-100 rounded-lg ring-1 ring-slate-200">
                    @foreach ($payFees as $fee)
                        <li class="flex items-center gap-3 px-3 py-2 text-sm">
                            <input type="checkbox" id="fee-{{ $fee->id }}" value="{{ $fee->id }}" wire:model.live="feeIds" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <label for="fee-{{ $fee->id }}" class="min-w-0 flex-1 truncate text-slate-700">{{ $fee->concept }}</label>
                            <span class="tabular-nums text-slate-900">{{ money($fee->balance()) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Importe" for="amount" error="amount" required>
                    <input id="amount" type="number" step="0.01" min="0" wire:model="amount" class="form-input">
                </x-field>
                <x-field label="Fecha" for="paymentDate" error="paymentDate" required>
                    <input id="paymentDate" type="date" wire:model="paymentDate" class="form-input">
                </x-field>
                <x-field label="Medio de pago" for="method" error="method" required>
                    <select id="method" wire:model="method" class="form-input">
                        @foreach ($methods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Referencia" for="reference" error="reference">
                    <input id="reference" wire:model="reference" class="form-input" placeholder="Opcional">
                </x-field>
            </div>
            <p class="text-xs text-slate-500">Si el importe es menor al saldo, se imputa a los cargos más antiguos primero.</p>
        </div>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="button" wire:click="pay" class="btn-primary">Registrar cobro</button>
        </x-slot:footer>
    </x-modal>
</div>
