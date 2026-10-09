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
        <x-stat-card label="Cobrado" :value="money($summary['paid'])" icon="banknotes" />
        <x-stat-card label="Adeudado" :value="money($summary['owed'])" icon="warning" />
        <x-stat-card label="Deben" :value="$summary['debtors']" icon="clock" />
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
                                    <td class="text-right tabular-nums">{{ money($participant->fee?->paid_amount ?? 0) }}</td>
                                    <td class="text-right tabular-nums">{{ $participant->fee && $participant->fee->isOpen() ? money($participant->fee->balance()) : '—' }}</td>
                                    <td class="text-right">
                                        @unless ($tournament->isCancelled())
                                            <button type="button" wire:click="remove({{ $participant->member_id }})" wire:confirm="¿Quitar a {{ $participant->member->fullName() }} del torneo? Se anula su cargo." class="btn-ghost btn-sm text-red-600" title="Quitar"><x-icon name="trash" class="size-4" /></button>
                                        @endunless
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

    <x-modal wire:model="showCancel" title="Cancelar torneo" max-width="max-w-md">
        <p class="text-sm text-slate-600">Se anulan los cargos de quienes no pagaron. A quienes ya pagaron tenés que anularles el pago o devolverles el dinero.</p>
        <x-field label="Motivo" for="cancelReason" error="cancelReason" required class="mt-4">
            <input id="cancelReason" wire:model="cancelReason" class="form-input">
        </x-field>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Volver</button>
            <button type="button" wire:click="cancelTournament" class="btn-primary bg-red-600 hover:bg-red-700">Cancelar torneo</button>
        </x-slot:footer>
    </x-modal>
</div>
