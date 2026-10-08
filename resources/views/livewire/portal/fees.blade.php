<div class="space-y-6">
    <h1 class="font-display text-2xl font-bold text-slate-900">Mi cuenta</h1>

    <div class="card flex flex-wrap items-center justify-between gap-4 p-5">
        <div>
            <p class="text-sm text-slate-500">Saldo pendiente</p>
            <p class="font-display text-3xl font-bold tabular-nums {{ bccomp($balance, '0', 2) > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ money($balance) }}</p>
        </div>
        @if (setting('club.payment_instructions'))
            <div class="max-w-md rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-line text-slate-600">{{ setting('club.payment_instructions') }}</div>
        @endif
    </div>

    <div class="flex gap-1 border-b border-slate-200">
        @foreach (['pendientes' => 'Pendientes', 'pagos' => 'Mis pagos', 'historial' => 'Historial'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class(['-mb-px border-b-2 px-4 py-2.5 text-sm font-medium', 'border-brand-600 text-brand-700' => $tab === $key, 'border-transparent text-slate-500' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'pendientes')
        <div class="space-y-3">
            @forelse ($openFees as $fee)
                <div class="card flex items-center justify-between gap-4 p-4">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-800">{{ $fee->concept }}</p>
                        <p class="text-xs text-slate-500">Vence {{ $fee->due_date->format('d/m/Y') }}@if ((float) $fee->surcharge > 0) · incluye recargo {{ money($fee->surcharge) }}@endif</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-slate-900 tabular-nums">{{ money($fee->balance()) }}</p>
                        <x-badge :status="$fee->status" />
                    </div>
                </div>
            @empty
                <div class="card"><x-empty-state icon="check-circle" title="¡Estás al día!" description="No tenés cuotas pendientes." /></div>
            @endforelse
        </div>
    @elseif ($tab === 'pagos')
        <div class="space-y-3">
            @forelse ($payments as $payment)
                <div class="card flex items-center justify-between gap-4 p-4">
                    <div>
                        <p class="font-medium text-slate-800">Recibo {{ $payment->receipt_number }}</p>
                        <p class="text-xs text-slate-500">{{ $payment->payment_date->format('d/m/Y') }} · {{ $payment->method->label() }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <p class="font-semibold tabular-nums">{{ money($payment->amount) }}</p>
                            <x-badge :status="$payment->status" />
                        </div>
                        <a href="{{ route('portal.receipt', $payment) }}" target="_blank" class="btn-ghost btn-sm" aria-label="Ver recibo"><x-icon name="printer" class="size-5" /></a>
                    </div>
                </div>
            @empty
                <div class="card"><x-empty-state icon="banknotes" title="Sin pagos registrados" /></div>
            @endforelse
        </div>
    @else
        <div class="card divide-y divide-slate-100">
            @forelse ($history as $fee)
                <div class="flex items-center justify-between gap-4 px-4 py-3">
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $fee->concept }}</p>
                        <p class="text-xs text-slate-500">{{ $fee->due_date->format('d/m/Y') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm tabular-nums">{{ money($fee->total()) }}</p>
                        <x-badge :status="$fee->status" />
                    </div>
                </div>
            @empty
                <x-empty-state icon="document" title="Sin movimientos anteriores" />
            @endforelse
        </div>
    @endif
</div>
