<div>
    <x-page-header :title="$tournament->name">
        <x-slot:breadcrumb><a href="{{ route('admin.tournaments.index') }}" wire:navigate class="hover:text-brand-700">Torneos</a> /</x-slot:breadcrumb>
        <x-slot:actions>
            @unless ($tournament->isCancelled())
                @can('mensajes.enviar')
                    @if ($summary['debtors'] > 0)
                        <a href="{{ route('admin.member-messages', ['torneo' => $tournament->id]) }}" wire:navigate class="btn-secondary"><x-icon name="chat" class="size-4" /> Avisar a los que deben ({{ $summary['debtors'] }})</a>
                    @endif
                @endcan
                <a href="{{ route('admin.tournaments.edit', $tournament) }}" wire:navigate class="btn-secondary"><x-icon name="pencil" class="size-4" /> Editar</a>
                <button type="button" wire:click="$set('showCancel', true)" class="btn-ghost text-red-600">Cancelar torneo</button>
            @endunless
        </x-slot:actions>
    </x-page-header>

    @if ($tournament->isCancelled())
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">Torneo cancelado el {{ $tournament->cancelled_at->format('d/m/Y') }}{{ $tournament->cancel_reason ? ': '.$tournament->cancel_reason : '' }}.</div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Anotados" :value="$summary['participants']" icon="users" />
        @if ($tournament->isCancelled())
            <x-stat-card label="Cobrado sin devolver" :value="money($summary['toRefund'])" icon="banknotes" />
            <x-stat-card label="Devuelto" :value="money($summary['refunded'])" icon="refresh" />
            <x-stat-card label="Quedó a cuenta" :value="money($summary['credited'])" icon="credit-card" />
        @else
            <x-stat-card label="Cobrado" :value="money($summary['paid'])" icon="banknotes" />
            <x-stat-card label="Adeudado" :value="money($summary['owed'])" icon="warning" />
            <x-stat-card label="Deben" :value="$summary['debtors']" icon="clock" />
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4">
                    <h2 class="font-semibold text-slate-900">Participantes</h2>
                    <div class="inline-flex rounded-lg bg-slate-100 p-1 text-sm">
                        @foreach (['todos' => 'Todos', 'deben' => 'Deben', 'pagaron' => 'Pagaron'] as $value => $label)
                            <button type="button" wire:click="$set('filter', '{{ $value }}')" @class(['rounded-md px-3 py-1 font-medium', 'bg-white text-slate-900 shadow-sm' => $filter === $value, 'text-slate-500' => $filter !== $value])>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Alumno</th><th>Estado</th><th class="text-right">Pagado</th><th class="text-right">Debe</th><th></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($participants as $participant)
                                <tr wire:key="p-{{ $participant->id }}">
                                    <td>
                                        <a href="{{ route('admin.members.show', $participant->member) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $participant->member->sortableName() }}</a>
                                        <span class="block text-xs text-slate-500">
                                            {{ $participant->source === 'portal' ? 'Se anotó desde el portal' : 'Anotado por el profe' }}
                                            @if ($participant->is_exception) · <span class="text-amber-700">Excepción</span>@endif
                                        </span>
                                    </td>
                                    <td>
                                        @if ($participant->fee)
                                            <x-badge :status="$participant->fee->status" />
                                        @else
                                            <x-badge color="gray">Sin cargo</x-badge>
                                        @endif
                                    </td>
                                    @php($refunded = $participant->fee?->refunds->sum('amount') ?? 0)
                                    @php($credited = $participant->fee?->credits->sum('amount') ?? 0)
                                    <td class="text-right tabular-nums">
                                        {{ money($participant->fee?->paid_amount ?? 0) }}
                                        @if ($refunded > 0)
                                            <span class="block text-xs text-slate-500">devuelto {{ money($refunded) }}</span>
                                        @endif
                                        @if ($credited > 0)
                                            <span class="block text-xs text-emerald-700">a cuenta {{ money($credited) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right tabular-nums">{{ $participant->fee && $participant->fee->isOpen() ? money($participant->fee->balance()) : '—' }}</td>
                                    <td class="text-right">
                                        @if (! $tournament->isCancelled())
                                            <button type="button" wire:click="remove({{ $participant->member_id }})" wire:confirm="¿Quitar a {{ $participant->member->fullName() }} del torneo? Se anula su cargo." class="btn-ghost btn-sm text-red-600" title="Quitar"><x-icon name="trash" class="size-4" /></button>
                                        @elseif ($participant->fee && (float) $participant->fee->paid_amount > 0)
                                            <button type="button" wire:click="askRefund({{ $participant->fee->id }})" class="btn-secondary btn-sm whitespace-nowrap">Devolver o dejar a cuenta</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-empty-state icon="users" title="Sin participantes" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-slate-100 px-4 py-3 text-xs text-slate-500">El torneo se cobra como cualquier cuota: desde <em>Cobrar en efectivo</em>, <em>Registrar pago</em> o con el comprobante que sube el alumno. Se puede pagar junto con la cuota o por separado.</p>
            </div>
        </div>

        <div class="space-y-6">
            @unless ($tournament->isCancelled())
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-slate-900">Anotar alumnos</h2>
                    @if ($pendingEligible > 0)
                        <button type="button" wire:click="addAllEligible" wire:confirm="¿Anotar a los {{ $pendingEligible }} alumnos de los {{ mb_strtolower(activity_label(true)) }} del torneo que faltan? A cada uno se le genera el cargo." class="btn-secondary mb-3 w-full">Anotar a todos los de los {{ mb_strtolower(activity_label(true)) }} ({{ $pendingEligible }})</button>
                    @endif
                    <div class="relative">
                        <input wire:model.live.debounce.300ms="memberSearch" class="form-input" placeholder="Buscar alumno por nombre o documento" autocomplete="off" aria-label="Buscar alumno">
                        @if ($results->isNotEmpty())
                            <ul class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-slate-200">
                                @foreach ($results as $result)
                                    <li><button type="button" wire:click="add({{ $result->id }})" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50">
                                        {{ $result->sortableName() }}
                                        @unless (in_array($result->id, $eligibleIds)) <span class="text-xs text-amber-700">· excepción</span>@endunless
                                    </button></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Podés sumar a cualquier alumno; si no es de los {{ mb_strtolower(activity_label(true)) }} del torneo queda marcado como excepción.</p>
                </div>
            @endunless

            @can('articulos.gestionar')
                <div class="card p-5 text-sm">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="font-semibold text-slate-900">Vestimenta y adicionales</h2>
                        @unless ($tournament->isCancelled())
                            <a href="{{ route('admin.extra-charges.create', ['torneo' => $tournament->id]) }}" wire:navigate class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Agregar</a>
                        @endunless
                    </div>
                    <ul class="space-y-2">
                        @forelse ($extraCharges as $charge)
                            <li>
                                <a href="{{ route('admin.extra-charges.show', $charge) }}" wire:navigate class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 p-3 hover:bg-slate-100">
                                    <span><span class="font-medium text-slate-800">{{ $charge->name }}</span> <span class="text-xs text-slate-500">· {{ money($charge->price) }} · {{ $charge->charged_count }} alumnos</span></span>
                                    @if ($charge->debtors_count > 0)
                                        <span class="text-xs font-semibold text-red-600">{{ $charge->debtors_count }} deben</span>
                                    @else
                                        <span class="text-xs font-semibold text-emerald-700">al día</span>
                                    @endif
                                </a>
                            </li>
                        @empty
                            <li class="text-slate-500">Ej.: la malla o la remera que hay que comprar para el torneo.</li>
                        @endforelse
                    </ul>
                </div>
            @endcan

            <div class="card p-5 text-sm">
                <h2 class="mb-3 font-semibold text-slate-900">Datos</h2>
                <dl class="space-y-2">
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">Costo</dt><dd class="font-medium">{{ money($tournament->price) }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">Pagar hasta</dt><dd class="font-medium">{{ $tournament->payment_due_date->format('d/m/Y') }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">Participan</dt><dd class="font-medium">{{ $tournament->is_open ? 'Libre (se anotan desde el portal)' : 'Solo los que elige el profe' }}</dd></div>
                </dl>
                <h3 class="mt-4 mb-2 font-medium text-slate-900">Días</h3>
                <ul class="space-y-2">
                    @foreach ($tournament->days as $day)
                        <li class="rounded-lg bg-slate-50 p-3">
                            <p class="font-medium text-slate-800">{{ ucfirst($day->date->translatedFormat('l d/m/Y')) }}</p>
                            <p class="text-xs text-slate-500">{{ $day->activities->pluck('name')->implode(', ') }}{{ $day->notes ? ' · '.$day->notes : '' }}</p>
                        </li>
                    @endforeach
                </ul>
                @if ($tournament->description)
                    <p class="mt-4 whitespace-pre-line text-slate-600">{{ $tournament->description }}</p>
                @endif
            </div>
        </div>
    </div>

    <x-modal wire:model="showRefund" title="¿Qué hacemos con la plata?" max-width="max-w-md">
        <div class="space-y-4">
            <fieldset class="space-y-2">
                <label class="flex items-start gap-2 text-sm">
                    <input type="radio" wire:model.live="refundDestination" value="devolver" class="mt-0.5 border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span><strong>Se la devuelvo</strong><span class="block text-slate-500">Registrá cómo y cuándo se la devolviste.</span></span>
                </label>
                <label class="flex items-start gap-2 text-sm">
                    <input type="radio" wire:model.live="refundDestination" value="a_cuenta" class="mt-0.5 border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span><strong>Queda a cuenta</strong><span class="block text-slate-500">Saldo a favor: lo usás después para pagar otra cosa (ej. la próxima cuota) al cobrarle.</span></span>
                </label>
            </fieldset>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Importe" for="refundAmount" error="refundAmount" required :help="$refundPaid ? 'Pagó '.money($refundPaid).'.' : null">
                    <input id="refundAmount" type="number" step="0.01" min="0" wire:model="refundAmount" class="form-input">
                </x-field>
                @if ($refundDestination === 'devolver')
                    <x-field label="Fecha" for="refundDate" error="refundDate" required>
                        <input id="refundDate" type="date" wire:model="refundDate" max="{{ today()->toDateString() }}" class="form-input">
                    </x-field>
                @endif
            </div>
            @if ($refundDestination === 'devolver')
                <x-field label="¿Cómo se devolvió?" for="refundMethod" error="refundMethod" required>
                    <select id="refundMethod" wire:model="refundMethod" class="form-input">
                        @foreach (\App\Enums\PaymentMethod::manualOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
            @endif
            <p class="text-xs text-slate-500">Si no se puede devolver ni dejar a cuenta, no registres nada: el cargo ya está anulado y no debe.</p>
            <x-field label="Nota (opcional)" for="refundNotes" error="refundNotes">
                <input id="refundNotes" wire:model="refundNotes" class="form-input" placeholder="ej. N° de transferencia">
            </x-field>
        </div>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="button" wire:click="confirmRefund" class="btn-primary">Confirmar</button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showCancel" title="Cancelar torneo" max-width="max-w-md">
        <p class="text-sm text-slate-600">Se anulan todos los cargos del torneo: nadie queda debiendo. A quienes ya pagaron, si se puede, les registrás la devolución desde la lista de participantes.</p>
        <x-field label="Motivo" for="cancelReason" error="cancelReason" required class="mt-4">
            <input id="cancelReason" wire:model="cancelReason" class="form-input">
        </x-field>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Volver</button>
            <button type="button" wire:click="cancelTournament" class="btn-primary bg-red-600 hover:bg-red-700">Cancelar torneo</button>
        </x-slot:footer>
    </x-modal>
</div>
