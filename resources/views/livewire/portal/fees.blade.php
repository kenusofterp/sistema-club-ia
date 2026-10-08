<div class="space-y-6">
    <h1 class="font-display text-2xl font-bold text-slate-900">Mi cuenta</h1>

    <div class="card p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">Saldo pendiente</p>
                <p class="font-display text-3xl font-bold tabular-nums {{ bccomp($balance, '0', 2) > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ money($balance) }}</p>
            </div>
            @if ($receiptsEnabled && $openFees->isNotEmpty())
                <button type="button" wire:click="openReport" class="btn-primary w-full sm:w-auto"><x-icon name="banknotes" class="size-5" /> Informar un pago</button>
            @endif
        </div>
        @if (setting('club.payment_instructions'))
            <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-line text-slate-600">{{ setting('club.payment_instructions') }}</div>
        @endif
    </div>

    <div class="-mx-4 flex gap-1 overflow-x-auto border-b border-slate-200 px-4 sm:mx-0 sm:px-0">
        @foreach (['pendientes' => 'Pendientes', ...($receiptsEnabled ? ['comprobantes' => 'Comprobantes'] : []), 'pagos' => 'Mis pagos', 'historial' => 'Historial'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class(['-mb-px border-b-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap', 'border-brand-600 text-brand-700' => $tab === $key, 'border-transparent text-slate-500' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'pendientes')
        <div class="space-y-3">
            @forelse ($openFees as $fee)
                <div class="card p-4" wire:key="fee-{{ $fee->id }}">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-800">{{ $fee->concept }}</p>
                            <p class="text-xs text-slate-500">Vence {{ $fee->due_date->format('d/m/Y') }}@if ((float) $fee->surcharge > 0) · incluye recargo {{ money($fee->surcharge) }}@endif</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-slate-900 tabular-nums">{{ money($fee->balance()) }}</p>
                            <x-badge :status="$fee->status" />
                        </div>
                    </div>
                    @if ($fee->receipts->isNotEmpty())
                        <p class="mt-2 rounded-lg bg-amber-50 px-3 py-1.5 text-xs text-amber-800">Informaste el pago: comprobante en revisión.</p>
                    @elseif ($receiptsEnabled && ! $fee->instructor_id)
                        <button type="button" wire:click="openReport({{ $fee->id }})" class="mt-2 text-sm font-medium text-brand-700 hover:underline">Informar pago de esta cuota</button>
                    @endif
                </div>
            @empty
                <div class="card"><x-empty-state icon="check-circle" title="¡Estás al día!" description="No tenés cuotas pendientes." /></div>
            @endforelse
        </div>
    @elseif ($tab === 'comprobantes')
        <div class="space-y-3">
            @forelse ($receipts as $receipt)
                <div class="card flex items-center justify-between gap-4 p-4" wire:key="rc-{{ $receipt->id }}">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-800">Transferencia {{ money($receipt->amount) }}</p>
                        <p class="text-xs text-slate-500">Del {{ $receipt->transfer_date->format('d/m/Y') }} · informado el {{ $receipt->created_at->format('d/m/Y') }}</p>
                        @if ($receipt->reject_reason)
                            <p class="mt-1 text-xs text-red-700">Motivo: {{ $receipt->reject_reason }}</p>
                        @endif
                    </div>
                    <x-badge :status="$receipt->status" />
                </div>
            @empty
                <div class="card"><x-empty-state icon="document" title="Todavía no informaste pagos" description="Si pagás por transferencia, subí el comprobante desde «Informar un pago»." /></div>
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

    {{-- Informar un pago: hoja inferior en el teléfono, modal en pantallas grandes --}}
    <div x-data="{ open: @entangle('showReport').live }" x-show="open" x-cloak class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-slate-900/50" x-on:click="open = false"></div>
        <form wire:submit="submitReport"
              class="absolute inset-x-0 bottom-0 max-h-[92vh] overflow-y-auto rounded-t-2xl bg-white p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-xl sm:inset-auto sm:top-1/2 sm:left-1/2 sm:w-full sm:max-w-lg sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl">
            <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-slate-200 sm:hidden"></div>
            <h3 class="text-lg font-semibold text-slate-900">Informar un pago</h3>

            @if ($info = setting('payments.transfer_info'))
                <div class="mt-3 rounded-lg bg-brand-50 p-3 text-sm whitespace-pre-line text-brand-900">
                    <p class="mb-1 font-semibold">Datos para transferir</p>{{ $info }}
                </div>
            @endif

            <p class="form-label mt-4">¿Qué estás pagando?</p>
            <ul class="divide-y divide-slate-100 rounded-lg ring-1 ring-slate-200">
                @foreach ($reportFees as $fee)
                    <li>
                        <label class="flex items-center gap-3 px-3 py-3 text-sm">
                            <input type="checkbox" value="{{ $fee->id }}" wire:model.live="feeIds" class="size-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <span class="min-w-0 flex-1 text-slate-700">{{ $fee->concept }}</span>
                            <span class="tabular-nums text-slate-900">{{ money($fee->balance()) }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
            @error('feeIds')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

            <div class="mt-4 grid grid-cols-2 gap-3">
                <div>
                    <label for="amount" class="form-label">Importe transferido</label>
                    <input id="amount" type="number" inputmode="decimal" step="0.01" min="0" wire:model="amount" class="form-input">
                    @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="transferDate" class="form-label">Fecha</label>
                    <input id="transferDate" type="date" wire:model="transferDate" class="form-input">
                    @error('transferDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <label for="reference" class="form-label mt-3">N° de operación (opcional)</label>
            <input id="reference" wire:model="reference" class="form-input">

            {{-- Comprobante: foto o PDF. Las fotos grandes se achican en el teléfono antes de subirlas. --}}
            <div class="mt-4" x-data="{
                    uploading: false, name: '',
                    async pick(e) {
                        const file = e.target.files[0];
                        if (!file) return;
                        this.name = file.name;
                        this.uploading = true;
                        let toSend = file;
                        if (file.type.startsWith('image/') && file.type !== 'image/heic' && file.size > 700 * 1024) {
                            try {
                                const bitmap = await createImageBitmap(file);
                                const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
                                const canvas = document.createElement('canvas');
                                canvas.width = Math.round(bitmap.width * scale);
                                canvas.height = Math.round(bitmap.height * scale);
                                canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                                const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.8));
                                if (blob) toSend = new File([blob], 'comprobante.jpg', { type: 'image/jpeg' });
                            } catch (err) { /* se sube el original */ }
                        }
                        $wire.upload('file', toSend, () => { this.uploading = false }, () => { this.uploading = false; this.name = '' });
                    }
                }">
                <p class="form-label">Comprobante</p>
                <label class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-slate-300 p-5 text-center text-sm text-slate-600 hover:border-brand-400 hover:bg-brand-50/40">
                    <x-icon name="photo" class="size-8 text-brand-600" />
                    <span x-show="!name">Sacá una foto o elegí el archivo (imagen o PDF)</span>
                    <span x-show="name" x-cloak class="font-medium text-slate-800" x-text="uploading ? 'Subiendo…' : name"></span>
                    <input type="file" accept="image/*,application/pdf" class="sr-only" x-on:change="pick">
                </label>
                @error('file')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="mt-5 grid grid-cols-2 gap-2">
                <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="file,submitReport">Enviar</button>
            </div>
        </form>
    </div>
</div>
