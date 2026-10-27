{{-- Tabla de resultados de una categoría (panel e imprimible). $rows: CompetitionRankingService::table(). --}}
@php($fmt = fn ($n) => $n === null ? '—' : number_format((float) $n, 3, ',', '.'))
<div class="card overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th class="w-16 text-center">Puesto</th>
                <th class="w-16">Dorsal</th>
                <th>Participante</th>
                <th>Club</th>
                @foreach ($exercises as $exercise)
                    <th class="text-right">{{ $exercise->name() }}</th>
                @endforeach
                <th class="text-right">All-around</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($rows as $row)
                <tr>
                    <td class="text-center font-display text-lg font-bold text-slate-900">{{ $row->rank }}</td>
                    <td class="tabular-nums">{{ $row->bib_number }}</td>
                    <td class="font-medium text-slate-900">
                        {{ $row->last_name }}, {{ $row->first_name }}
                        @if ($row->incomplete)
                            <span class="ml-1 text-xs font-normal text-amber-700">incompleta ({{ $row->done }}/{{ $row->expected }})</span>
                        @endif
                    </td>
                    <td>{{ $row->institution ?? '—' }}</td>
                    @foreach ($exercises as $exercise)
                        @php($result = $row->exercises[$exercise->id] ?? null)
                        <td class="text-right tabular-nums">
                            {{ $fmt($result['score'] ?? null) }}
                            @if ($result)
                                <span class="block text-xs text-slate-500">{{ $result['rank'] }}°</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="text-right font-display text-base font-bold text-slate-900 tabular-nums">{{ $fmt($row->score) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 5 + $exercises->count() }}"><x-empty-state icon="chart" title="Todavía no hay notas publicadas" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
