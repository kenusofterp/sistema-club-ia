<div wire:poll.10s.visible>
    <x-page-header title="Modificaciones de notas" subtitle="Una nota confirmada solo cambia con autorización. Queda registrado quién pidió, quién autorizó, el motivo y los valores anteriores y nuevos.">
        <x-slot:actions>
            <div class="inline-flex rounded-lg bg-slate-100 p-1 text-sm">
                @foreach (['pendientes' => "Pendientes ({$pendingCount})", 'todas' => 'Historial'] as $value => $label)
                    <button type="button" wire:click="$set('filter', '{{ $value }}')" @class(['rounded-md px-3 py-1 font-medium', 'bg-white text-slate-900 shadow-sm' => $filter === $value, 'text-slate-500' => $filter !== $value])>{{ $label }}</button>
                @endforeach
            </div>
        </x-slot:actions>
    </x-page-header>

    @php($fmt = fn ($n) => $n === null ? '—' : number_format((float) $n, 3, ',', '.'))

    <div class="space-y-4">
        @forelse ($requests as $request)
            @php($evaluation = $request->evaluation)
            <div class="card p-5" wire:key="req-{{ $request->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900">
                            {{ $evaluation->entry->bib_number }} · {{ $evaluation->entry->competitor->fullName() }}
                            <span class="font-normal text-slate-500">— {{ $evaluation->competitionExercise->name() }}, {{ $evaluation->station->name }}</span>
                        </p>
                        <p class="text-sm text-slate-500">{{ $request->competition->name }} · nota del juez {{ $request->sheet->seat }} ({{ $request->sheet->user->name }})</p>
                    </div>
                    <x-badge :status="$request->status" />
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <table class="w-full text-sm">
                            <thead><tr class="text-left text-xs text-slate-500 uppercase"><th class="py-1 font-medium">Criterio</th><th class="py-1 text-right font-medium">Antes</th><th class="py-1 text-right font-medium">Pedido</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($request->new_values as $criterionId => $value)
                                    @php($criterion = $criteria->get($criterionId))
                                    @php($old = $request->old_values[$criterionId] ?? null)
                                    @php($isYesNo = $criterion?->type === \App\Enums\CriterionType::YesNo)
                                    <tr @class(['font-semibold text-amber-800' => (string) $old !== (string) $value])>
                                        <td class="py-1.5">{{ $criterion?->name ?? "#{$criterionId}" }}</td>
                                        <td class="py-1.5 text-right tabular-nums">{{ $isYesNo ? ((float) $old ? 'Sí' : 'No') : $fmt($old) }}</td>
                                        <td class="py-1.5 text-right tabular-nums">{{ $isYesNo ? ((float) $value ? 'Sí' : 'No') : $fmt($value) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="border-t-2 border-slate-200 font-semibold text-slate-900">
                                    <td class="py-1.5">Total del juez</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ $fmt($request->old_total) }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ $fmt($request->new_total) }}</td>
                                </tr>
                                <tr class="text-slate-600">
                                    <td class="py-1.5">Puntaje del ejercicio</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ $fmt($request->old_score) }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ $request->new_score !== null ? $fmt($request->new_score) : ($request->isPending() ? 'se recalcula' : '—') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="space-y-3 text-sm">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <p class="text-xs font-medium text-slate-500 uppercase">Motivo</p>
                            <p class="text-slate-800">{{ $request->reason }}</p>
                            <p class="mt-1 text-xs text-slate-500">Pidió {{ $request->requester->name }} · {{ $request->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        @if ($request->isPending())
                            @can('resolve', $request)
                                <input wire:model="notes.{{ $request->id }}" class="form-input text-sm" placeholder="Nota (opcional)" aria-label="Nota de la resolución">
                                <div class="flex gap-2">
                                    <button type="button" wire:click="approve({{ $request->id }})" wire:confirm="¿Aplicar la modificación? Se recalculan el puntaje y los rankings." class="btn-primary flex-1">Autorizar</button>
                                    <button type="button" wire:click="reject({{ $request->id }})" class="btn-secondary flex-1 text-red-600">Rechazar</button>
                                </div>
                            @else
                                <p class="text-xs text-slate-500">Lo tiene que autorizar otra persona con permiso.</p>
                            @endcan
                        @else
                            <div class="rounded-lg bg-slate-50 p-3">
                                <p class="text-xs font-medium text-slate-500 uppercase">{{ $request->status->label() }}</p>
                                <p class="text-slate-800">{{ $request->resolver?->name }} · {{ $request->resolved_at?->format('d/m/Y H:i') }}</p>
                                @if ($request->resolution_note)
                                    <p class="mt-1 text-slate-600">{{ $request->resolution_note }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="card"><x-empty-state icon="shield" :title="$filter === 'pendientes' ? 'No hay pedidos pendientes' : 'Sin modificaciones registradas'" /></div>
        @endforelse
    </div>
</div>
