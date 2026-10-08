<div>
    <x-page-header title="Comprobantes" subtitle="Pagos por transferencia informados por los socios desde la app" />

    @if ($autoApprove)
        <p class="mb-4 rounded-lg bg-sky-50 p-3 text-sm text-sky-800">Los comprobantes se acreditan automáticamente (Configuración › Cobros). Si alguno está mal, anulá el pago desde <a href="{{ route('admin.payments.index') }}" wire:navigate class="font-medium underline">Pagos</a>.</p>
    @endif

    <div class="-mx-4 mb-4 flex gap-1 overflow-x-auto border-b border-slate-200 px-4 sm:mx-0 sm:px-0">
        @foreach (['pendiente' => 'En revisión'.($pendingCount ? " ({$pendingCount})" : ''), 'aprobado' => 'Acreditados', 'rechazado' => 'Rechazados', '' => 'Todos'] as $key => $label)
            <button type="button" wire:click="$set('status', '{{ $key }}')" @class(['-mb-px border-b-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap', 'border-brand-600 text-brand-700' => $status === $key, 'border-transparent text-slate-500' => $status !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($receipts as $receipt)
            <button type="button" wire:click="view({{ $receipt->id }})" wire:key="r-{{ $receipt->id }}" class="card flex w-full items-center gap-4 p-4 text-left hover:shadow-md">
                <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-100">
                    @if ($receipt->isPdf())
                        <x-icon name="document" class="size-6 text-slate-500" />
                    @else
                        <img src="{{ $receipt->fileUrl() }}" alt="" class="size-12 object-cover" loading="lazy">
                    @endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-medium text-slate-900">{{ $receipt->member->fullName() }}</span>
                    <span class="block truncate text-sm text-slate-500">{{ $receipt->fees->pluck('concept')->implode(', ') }}</span>
                    <span class="block text-xs text-slate-400">Transferido el {{ $receipt->transfer_date->format('d/m/Y') }} · informado {{ $receipt->created_at->diffForHumans() }}</span>
                </span>
                <span class="shrink-0 text-right">
                    <span class="block font-semibold tabular-nums text-slate-900">{{ money($receipt->amount) }}</span>
                    <x-badge :status="$receipt->status" />
                </span>
            </button>
        @empty
            <div class="card"><x-empty-state icon="document" title="No hay comprobantes" description="Los socios informan pagos desde «Mi cuenta» en la app." /></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $receipts->links() }}</div>

    <x-modal wire:model="showView" title="Comprobante" max-width="max-w-2xl">
        @if ($viewing)
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200">
                    @if ($viewing->isPdf())
                        <a href="{{ $viewing->fileUrl() }}" target="_blank" class="flex h-48 flex-col items-center justify-center gap-2 text-sm font-medium text-brand-700"><x-icon name="document" class="size-10" /> Abrir PDF</a>
                    @else
                        <a href="{{ $viewing->fileUrl() }}" target="_blank"><img src="{{ $viewing->fileUrl() }}" alt="Comprobante" class="max-h-96 w-full object-contain"></a>
                    @endif
                </div>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-slate-500">Socio</dt><dd class="font-medium text-slate-900">{{ $viewing->member->fullName() }} <a href="{{ route('admin.members.show', $viewing->member) }}" wire:navigate class="text-xs text-brand-700">ver ficha</a></dd></div>
                    <div><dt class="text-slate-500">Importe</dt><dd class="font-display text-2xl font-bold tabular-nums text-slate-900">{{ money($viewing->amount) }}</dd></div>
                    <div><dt class="text-slate-500">Fecha de la transferencia</dt><dd>{{ $viewing->transfer_date->format('d/m/Y') }}{{ $viewing->reference ? ' · Op. '.$viewing->reference : '' }}</dd></div>
                    <div>
                        <dt class="text-slate-500">Cuotas</dt>
                        <dd>
                            <ul class="mt-1 space-y-1">
                                @foreach ($viewing->fees as $fee)
                                    <li class="flex justify-between gap-2"><span>{{ $fee->concept }}</span> <span class="tabular-nums">{{ money($fee->balance()) }} <x-badge :status="$fee->status" /></span></li>
                                @endforeach
                            </ul>
                        </dd>
                    </div>
                    @if ($viewing->reviewer || $viewing->reviewed_at)
                        <div><dt class="text-slate-500">Revisado</dt><dd>{{ $viewing->reviewer?->name ?? 'Automático' }} · {{ $viewing->reviewed_at?->format('d/m/Y H:i') }}</dd></div>
                    @endif
                    @if ($viewing->payment)
                        <div><dt class="text-slate-500">Pago</dt><dd>Recibo {{ $viewing->payment->receipt_number }}</dd></div>
                    @endif
                    @if ($viewing->reject_reason)
                        <div><dt class="text-slate-500">Motivo del rechazo</dt><dd class="text-red-700">{{ $viewing->reject_reason }}</dd></div>
                    @endif
                </dl>
            </div>

            @if ($viewing->status === \App\Enums\ReceiptStatus::Pending)
                @can('comprobantes.revisar')
                    <div class="mt-4">
                        <label for="rejectReason" class="form-label">Motivo si lo rechazás</label>
                        <input id="rejectReason" wire:model="rejectReason" class="form-input" placeholder="Ej.: no se acreditó la transferencia, el importe no coincide…">
                        @error('rejectReason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endcan
            @endif
        @endif
        <x-slot:footer>
            @if ($viewing && $viewing->status === \App\Enums\ReceiptStatus::Pending)
                @can('comprobantes.revisar')
                    <button type="button" wire:click="reject" class="btn-secondary text-red-600">Rechazar</button>
                    <button type="button" wire:click="approve" class="btn-primary"><x-icon name="check" class="size-4" /> Acreditar pago</button>
                @endcan
            @else
                <button type="button" class="btn-secondary" x-on:click="open = false">Cerrar</button>
            @endif
        </x-slot:footer>
    </x-modal>
</div>
