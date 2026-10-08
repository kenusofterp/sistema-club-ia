<div>
    <x-page-header title="Cuotas y cargos" subtitle="Cuenta corriente de todos los socios">
        <x-slot:actions>
            @can('reportes.ver')
                <a href="{{ route('admin.export', ['type' => 'cuotas', 'estado' => $status]) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> Exportar</a>
            @endcan
            @can('cuotas.gestionar')
                <button type="button" wire:click="processOverdue" wire:confirm="¿Procesar vencimientos ahora? Se aplicará el recargo configurado a los cargos vencidos." class="btn-secondary"><x-icon name="clock" class="size-4" /> Procesar vencimientos</button>
                <button type="button" wire:click="$set('showGenerate', true)" class="btn-primary"><x-icon name="refresh" class="size-4" /> Generar cuotas</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Facturado (filtro actual)" :value="money($totals->billed)" icon="document" />
        <x-stat-card label="Cobrado" :value="money($totals->paid)" icon="banknotes" tone="sky" />
        <x-stat-card label="Pendiente" :value="money($totals->billed - $totals->paid)" icon="warning" tone="red" />
    </div>

    <div class="card">
        <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-4">
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Buscar socio…" class="form-input">
            <select wire:model.live="status" class="form-input">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="type" class="form-input">
                <option value="">Todos los tipos</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <input type="month" wire:model.live="period" class="form-input" title="Período">
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Socio</th><th>Concepto</th><th>Vence</th><th class="text-right">Total</th><th class="text-right">Pagado</th><th class="text-right">Saldo</th><th>Estado</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($fees as $fee)
                        <tr wire:key="f-{{ $fee->id }}">
                            <td><a href="{{ route('admin.members.show', $fee->member) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $fee->member->sortableName() }}</a></td>
                            <td>
                                <p>{{ $fee->concept }}</p>
                                @if ($fee->cancel_reason)<p class="text-xs text-slate-400">Anulado: {{ $fee->cancel_reason }}</p>@endif
                            </td>
                            <td class="whitespace-nowrap">{{ $fee->due_date->format('d/m/Y') }}</td>
                            <td class="text-right tabular-nums">{{ money($fee->total()) }}</td>
                            <td class="text-right tabular-nums">{{ money($fee->paid_amount) }}</td>
                            <td class="text-right font-medium tabular-nums">{{ $fee->isOpen() ? money($fee->balance()) : '—' }}</td>
                            <td><x-badge :status="$fee->status" /></td>
                            <td class="text-right whitespace-nowrap">
                                @if ($fee->isOpen())
                                    @can('pagos.registrar')
                                        <a href="{{ route('admin.payments.create', ['socio' => $fee->member_id]) }}" wire:navigate class="btn-ghost btn-sm" title="Cobrar"><x-icon name="banknotes" class="size-4" /></a>
                                    @endcan
                                    @can('cuotas.gestionar')
                                        @if ((float) $fee->paid_amount == 0)
                                            <button type="button" wire:click="confirmCancel({{ $fee->id }})" class="btn-ghost btn-sm text-red-600" title="Anular"><x-icon name="ban" class="size-4" /></button>
                                        @endif
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="document" title="Sin cargos" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($fees->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $fees->links() }}</div>
        @endif
    </div>

    <x-modal wire:model="showGenerate" title="Generar cuotas del período" max-width="max-w-md">
        <p class="mb-4 text-sm text-slate-600">Crea la cuota social y las cuotas de actividades de todos los socios activos. Es seguro repetirlo: no duplica cargos ya generados.</p>
        <x-field label="Período" for="generatePeriod" error="generatePeriod" required>
            <input id="generatePeriod" type="month" wire:model="generatePeriod" class="form-input">
        </x-field>
        <p class="mt-3 text-xs text-slate-500">El proceso automático corre el día {{ setting('club.fee_generation_day', 1) }} de cada mes.</p>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="button" wire:click="generate" class="btn-primary" wire:loading.attr="disabled" wire:target="generate">
                <span wire:loading.remove wire:target="generate">Generar</span><span wire:loading wire:target="generate">Generando…</span>
            </button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showCancel" title="Anular cargo" max-width="max-w-md">
        <x-field label="Motivo de la anulación" for="cancelReason" error="cancelReason" required>
            <textarea id="cancelReason" wire:model="cancelReason" rows="3" class="form-input"></textarea>
        </x-field>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Volver</button>
            <button type="button" wire:click="cancel" class="btn-danger">Anular cargo</button>
        </x-slot:footer>
    </x-modal>
</div>
