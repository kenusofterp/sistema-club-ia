<div>
    <x-page-header :title="$extraCharge->name" :subtitle="$extraCharge->contextLabel().' · '.money($extraCharge->price).' · pagar hasta el '.$extraCharge->due_date->format('d/m/Y')">
        <x-slot:breadcrumb>
            @if ($extraCharge->tournament_id)
                <a href="{{ route('admin.tournaments.show', $extraCharge->tournament_id) }}" wire:navigate class="hover:text-brand-700">Torneo {{ $extraCharge->tournament?->name }}</a> /
            @else
                <a href="{{ route('admin.extra-charges.index') }}" wire:navigate class="hover:text-brand-700">Vestimenta y artículos</a> /
            @endif
        </x-slot:breadcrumb>
        <x-slot:actions>
            @can('mensajes.enviar')
                @if ($summary['debtors'] > 0)
                    <a href="{{ route('admin.member-messages', ['adicional' => $extraCharge->id]) }}" wire:navigate class="btn-secondary"><x-icon name="chat" class="size-4" /> Avisar a los que deben ({{ $summary['debtors'] }})</a>
                @endif
            @endcan
            <button type="button" wire:click="openAdd" class="btn-secondary"><x-icon name="user-plus" class="size-4" /> Cobrar también a…</button>
            <button type="button" wire:click="openEdit" class="btn-secondary"><x-icon name="pencil" class="size-4" /> Editar</button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Se les cobra" :value="$summary['charged']" icon="users" />
        <x-stat-card label="Cobrado" :value="money($summary['paid'])" icon="banknotes" />
        <x-stat-card label="Adeudado" :value="money($summary['owed'])" icon="warning" />
        <x-stat-card label="Anulados" :value="$summary['cancelled']" icon="ban" />
    </div>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Alumno</th><th>Estado</th><th class="text-right">Pagado</th><th class="text-right">Debe</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $fee)
                        @php($cancelled = $fee->status === \App\Enums\FeeStatus::Cancelled)
                        <tr wire:key="f-{{ $fee->id }}" @class(['opacity-60' => $cancelled])>
                            <td>
                                <a href="{{ route('admin.members.show', $fee->member) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $fee->member->sortableName() }}</a>
                                @if ($cancelled && $fee->cancel_reason)
                                    <span class="block text-xs text-slate-500">Anulado: {{ $fee->cancel_reason }}</span>
                                @endif
                            </td>
                            <td><x-badge :status="$fee->status" /></td>
                            <td class="text-right tabular-nums">{{ money($fee->paid_amount) }}</td>
                            <td class="text-right tabular-nums">{{ $fee->isOpen() ? money($fee->balance()) : '—' }}</td>
                            <td class="text-right">
                                @if (! $cancelled && (float) $fee->paid_amount == 0)
                                    <button type="button" wire:click="askCancel({{ $fee->member_id }})" class="btn-ghost btn-sm text-red-600">Anular</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="users" title="No se le cobró a nadie" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="border-t border-slate-100 px-4 py-3 text-xs text-slate-500">Se cobra como la cuota: desde <em>Cobrar en efectivo</em>, <em>Registrar pago</em> o con el comprobante que sube el alumno. Si un alumno ya pagó y después lo consiguió por otro lado, primero anulá ese pago.</p>
    </div>

    {{-- Anular a un alumno --}}
    <x-modal wire:model="showCancel" title="Anular el cargo" max-width="max-w-md">
        <p class="text-sm text-slate-600">Se le anula {{ $extraCharge->name }} solo a este alumno; a los demás no les cambia nada.</p>
        <x-field label="Motivo" for="cancelReason" error="cancelReason" required class="mt-4">
            <input id="cancelReason" wire:model="cancelReason" class="form-input">
        </x-field>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Volver</button>
            <button type="button" wire:click="confirmCancel" class="btn-primary bg-red-600 hover:bg-red-700">Anular</button>
        </x-slot:footer>
    </x-modal>

    {{-- Editar --}}
    <x-modal wire:model="showEdit" title="Editar adicional" max-width="max-w-md">
        <div class="space-y-4">
            <x-field label="Nombre" for="e-name" error="name" required><input id="e-name" wire:model="name" class="form-input"></x-field>
            <x-field label="Detalle" for="e-description" error="description"><input id="e-description" wire:model="description" class="form-input"></x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Precio" for="e-price" error="price" required><input id="e-price" type="number" step="0.01" min="0" wire:model="price" class="form-input"></x-field>
                <x-field label="Pagar hasta" for="e-due" error="due_date" required><input id="e-due" type="date" wire:model="due_date" @if ($extraCharge->tournament?->startsOn()) max="{{ $extraCharge->tournament->startsOn()->toDateString() }}" @endif class="form-input"></x-field>
            </div>
            <p class="text-xs text-slate-500">Se actualizan los cargos de quienes todavía no pagaron nada.</p>
        </div>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="button" wire:click="saveEdit" class="btn-primary">Guardar</button>
        </x-slot:footer>
    </x-modal>

    {{-- Cobrar también a… --}}
    <x-modal wire:model="showAdd" title="Cobrar también a…" max-width="max-w-md">
        <ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
            @forelse ($addCandidates as $member)
                <li wire:key="add-{{ $member->id }}">
                    <label class="flex cursor-pointer items-center gap-3 py-2 text-sm">
                        <input type="checkbox" value="{{ $member->id }}" wire:model="toAdd" class="size-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ $member->sortableName() }}
                    </label>
                </li>
            @empty
                <li class="py-6 text-center text-sm text-slate-500">Ya se les cobra a todos.</li>
            @endforelse
        </ul>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="button" wire:click="addMembers" class="btn-primary">Cobrar</button>
        </x-slot:footer>
    </x-modal>
</div>
