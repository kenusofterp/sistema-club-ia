<div>
    <x-page-header title="Pagos" subtitle="Cobranzas registradas">
        <x-slot:actions>
            @can('reportes.ver')
                <a href="{{ route('admin.export', ['type' => 'pagos', 'desde' => $from, 'hasta' => $to]) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> Exportar</a>
            @endcan
            @can('pagos.registrar')
                <a href="{{ route('admin.payments.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" class="size-4" /> Registrar pago</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Total cobrado (período)" :value="money($total)" icon="banknotes" />
        @foreach ($byMethod->sortDesc()->take(3) as $method => $amount)
            <x-stat-card :label="\App\Enums\PaymentMethod::from($method)->label()" :value="money($amount)" icon="credit-card" tone="sky" />
        @endforeach
    </div>

    <div class="card">
        <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-5">
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Recibo o socio…" class="form-input">
            <input type="date" wire:model.live="from" class="form-input" title="Desde">
            <input type="date" wire:model.live="to" class="form-input" title="Hasta">
            <select wire:model.live="method" class="form-input">
                <option value="">Todos los medios</option>
                @foreach ($methods as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="status" class="form-input">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Recibo</th><th>Fecha</th><th>Socio</th><th>Medio</th><th class="text-right">Importe</th><th>Estado</th><th class="hidden lg:table-cell">Registró</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $payment)
                        <tr wire:key="p-{{ $payment->id }}">
                            <td class="font-medium">{{ $payment->receipt_number }}</td>
                            <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                            <td><a href="{{ route('admin.members.show', $payment->member) }}" wire:navigate class="hover:text-brand-700">{{ $payment->member->sortableName() }}</a></td>
                            <td>{{ $payment->method->label() }}@if ($payment->reference)<span class="block text-xs text-slate-400">{{ $payment->reference }}</span>@endif</td>
                            <td class="text-right font-medium tabular-nums">{{ money($payment->amount) }}</td>
                            <td>
                                <x-badge :status="$payment->status" />
                                @if ($payment->cancel_reason)<span class="block text-xs text-slate-400">{{ $payment->cancel_reason }}</span>@endif
                            </td>
                            <td class="hidden lg:table-cell">{{ $payment->receiver?->name ?? '—' }}</td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('admin.payments.receipt', $payment) }}" target="_blank" class="btn-ghost btn-sm" title="Recibo"><x-icon name="printer" class="size-4" /></a>
                                @if (! $payment->isCancelled())
                                    @can('pagos.anular')
                                        <button type="button" wire:click="confirmCancel({{ $payment->id }})" class="btn-ghost btn-sm text-red-600" title="Anular"><x-icon name="ban" class="size-4" /></button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="banknotes" title="Sin pagos en el período" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $payments->links() }}</div>
        @endif
    </div>

    <x-modal wire:model="showCancel" title="Anular pago" max-width="max-w-md">
        <p class="mb-4 text-sm text-slate-600">El importe se desimputará de los cargos asociados, que volverán a quedar pendientes.</p>
        <x-field label="Motivo" for="cancelReason" error="cancelReason" required>
            <textarea id="cancelReason" wire:model="cancelReason" rows="3" class="form-input"></textarea>
        </x-field>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Volver</button>
            <button type="button" wire:click="cancel" class="btn-danger">Anular pago</button>
        </x-slot:footer>
    </x-modal>
</div>
