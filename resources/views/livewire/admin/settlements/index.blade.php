<div>
    <x-page-header title="Rendiciones" subtitle="Efectivo que cobran los profesores y entregan a la entidad" />

    @unless ($requiresSettlement)
        <p class="mb-4 rounded-lg bg-sky-50 p-3 text-sm text-sky-800">En esta entidad el efectivo no se rinde (Configuración › Cobros). Igual podés ver cuánto cobró cada profesor.</p>
    @endunless

    <div class="grid gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 font-semibold text-slate-900">Por confirmar</h2>
            <div class="space-y-3">
                @forelse ($pending as $settlement)
                    <div class="card p-4" wire:key="s-{{ $settlement->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <button type="button" wire:click="show({{ $settlement->id }})" class="min-w-0 text-left">
                                <p class="font-medium text-slate-900">{{ $settlement->user->name }}</p>
                                <p class="text-sm text-slate-500">{{ $settlement->payments_count }} cobros · {{ $settlement->created_at->format('d/m/Y H:i') }}</p>
                                @if ($settlement->notes)<p class="mt-1 text-sm text-slate-600">{{ $settlement->notes }}</p>@endif
                            </button>
                            <p class="font-display text-xl font-bold tabular-nums text-slate-900">{{ money($settlement->amount) }}</p>
                        </div>
                        @can('rendiciones.gestionar')
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button type="button" wire:click="revert({{ $settlement->id }})" wire:confirm="¿Devolver la rendición? Los cobros vuelven a quedar a cargo del profesor." class="btn-secondary">Devolver</button>
                                @if ($settlement->user_id !== auth()->id() || $selfConfirm)
                                    <button type="button" wire:click="confirm({{ $settlement->id }})" wire:confirm="¿Confirmás que recibiste {{ money($settlement->amount) }} de {{ $settlement->user->name }}?" class="btn-primary"><x-icon name="check" class="size-4" /> Recibí el dinero</button>
                                @else
                                    <p class="self-center text-center text-xs text-slate-500">Otra persona tiene que confirmar tu rendición.</p>
                                @endif
                            </div>
                        @endcan
                    </div>
                @empty
                    <div class="card p-5 text-sm text-slate-500">No hay rendiciones pendientes.</div>
                @endforelse
            </div>
        </section>

        <section>
            <h2 class="mb-3 font-semibold text-slate-900">Efectivo sin rendir</h2>
            <ul class="card divide-y divide-slate-100">
                @forelse ($inHand as $row)
                    <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium text-slate-800">{{ $row['user']?->name ?? '—' }}</p>
                            <p class="text-xs text-slate-500">{{ $row['count'] }} cobros</p>
                        </div>
                        <span class="font-semibold tabular-nums text-slate-900">{{ money($row['total']) }}</span>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-slate-500">Ningún profesor tiene efectivo sin rendir.</li>
                @endforelse
            </ul>

            <h2 class="mt-6 mb-3 font-semibold text-slate-900">Confirmadas</h2>
            <ul class="card divide-y divide-slate-100">
                @forelse ($confirmed as $settlement)
                    <li>
                        <button type="button" wire:click="show({{ $settlement->id }})" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm hover:bg-slate-50">
                            <span>
                                <span class="block font-medium text-slate-800">{{ $settlement->user->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $settlement->confirmed_at->format('d/m/Y') }} · recibió {{ $settlement->confirmer?->name }}</span>
                            </span>
                            <span class="font-semibold tabular-nums text-slate-900">{{ money($settlement->amount) }}</span>
                        </button>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-slate-500">Todavía no hay rendiciones confirmadas.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <x-modal wire:model="showDetail" title="Detalle de la rendición" max-width="max-w-lg">
        @if ($detail)
            <p class="mb-3 text-sm text-slate-600">{{ $detail->user->name }} · {{ money($detail->amount) }} · <x-badge :status="$detail->status" /></p>
            <ul class="divide-y divide-slate-100 rounded-lg ring-1 ring-slate-200">
                @foreach ($detail->payments as $payment)
                    <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                        <span class="min-w-0">
                            <span class="block truncate text-slate-800">{{ $payment->member->fullName() }}</span>
                            <span class="block text-xs text-slate-500">{{ $payment->payment_date->format('d/m/Y') }} · Recibo {{ $payment->receipt_number }}</span>
                        </span>
                        <span class="tabular-nums">{{ money($payment->amount) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cerrar</button>
        </x-slot:footer>
    </x-modal>
</div>
