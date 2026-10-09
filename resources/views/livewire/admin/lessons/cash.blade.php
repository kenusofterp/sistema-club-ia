<div class="mx-auto max-w-2xl">
    <div class="mb-4 flex items-center gap-3">
        <a href="{{ route('admin.lessons.today') }}" wire:navigate class="btn-ghost px-2" aria-label="Volver"><x-icon name="arrow-left" class="size-5" /></a>
        <h1 class="font-display text-xl font-bold text-slate-900">Efectivo a rendir</h1>
    </div>

    <div class="card p-5 text-center">
        <p class="text-sm text-slate-500">Tenés en tu poder</p>
        <p class="font-display text-4xl font-bold tabular-nums text-slate-900">{{ money($total) }}</p>
        <p class="text-sm text-slate-500">{{ $pending->count() }} {{ $pending->count() === 1 ? 'cobro' : 'cobros' }} sin rendir</p>

        @if ($pending->isNotEmpty())
            <input wire:model="notes" class="form-input mt-4" placeholder="Nota para quien recibe (opcional)" aria-label="Nota">
            <button type="button" wire:click="settle" wire:confirm="¿Rendir {{ money($total) }}? {{ $selfConfirm ? 'Después la confirmás vos en «Mis rendiciones».' : 'Entregá el dinero a coordinación para que lo confirme.' }}" class="btn-primary mt-3 w-full py-3 text-base">Rendir {{ money($total) }}</button>
        @endif
    </div>

    @if ($pending->isNotEmpty())
        <h2 class="mt-6 mb-2 font-semibold text-slate-900">Cobros sin rendir</h2>
        <ul class="card divide-y divide-slate-100">
            @foreach ($pending as $payment)
                <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-slate-800">{{ $payment->member->fullName() }}</p>
                        <p class="text-xs text-slate-500">{{ $payment->payment_date->format('d/m/Y') }} · Recibo {{ $payment->receipt_number }}</p>
                    </div>
                    <span class="tabular-nums font-medium text-slate-900">{{ money($payment->amount) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="mt-6 mb-2 font-semibold text-slate-900">Mis rendiciones</h2>
    <ul class="card divide-y divide-slate-100">
        @forelse ($settlements as $settlement)
            <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                <div>
                    <p class="font-medium tabular-nums text-slate-800">{{ money($settlement->amount) }} <span class="font-normal text-slate-500">· {{ $settlement->payments_count }} cobros</span></p>
                    <p class="text-xs text-slate-500">{{ $settlement->created_at->format('d/m/Y H:i') }}@if ($settlement->confirmed_at) · confirmada el {{ $settlement->confirmed_at->format('d/m/Y') }}@endif</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    @if ($selfConfirm && $settlement->status === \App\Enums\SettlementStatus::Pending)
                        <button type="button" wire:click="confirmOwn({{ $settlement->id }})" wire:confirm="¿Confirmás la rendición de {{ money($settlement->amount) }}?" class="btn-primary btn-sm"><x-icon name="check" class="size-4" /> Confirmar</button>
                    @endif
                    <x-badge :status="$settlement->status" />
                </div>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-slate-500">Todavía no rendiste efectivo.</li>
        @endforelse
    </ul>
</div>
