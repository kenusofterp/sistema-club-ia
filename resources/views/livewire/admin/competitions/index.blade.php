<div>
    <x-page-header title="Competencias" subtitle="Torneos con jueceo: puestos de 3 jueces, notas desde el celular y resultados en vivo">
        <x-slot:actions>
            <a href="{{ route('admin.exercises') }}" wire:navigate class="btn-secondary"><x-icon name="list" class="size-4" /> Ejercicios y criterios</a>
            <a href="{{ route('admin.competitions.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" class="size-4" /> Nueva competencia</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr><th>Competencia</th><th>Fecha</th><th class="text-right">Participantes</th><th class="text-right">Puestos</th><th>Avance</th><th>Estado</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($competitions as $competition)
                    <tr wire:key="competition-{{ $competition->id }}">
                        <td>
                            <a href="{{ route('admin.competitions.show', $competition) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $competition->name }}</a>
                            @if ($competition->location)
                                <span class="block text-xs text-slate-500">{{ $competition->location }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">{{ $competition->date->format('d/m/Y') }}</td>
                        <td class="text-right tabular-nums">{{ $competition->entries_count }}</td>
                        <td class="text-right tabular-nums">{{ $competition->stations_count }}</td>
                        <td class="min-w-32">
                            @if ($competition->evaluations_count > 0)
                                @php($pct = (int) round($competition->published_count * 100 / $competition->evaluations_count))
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 flex-1 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-brand-600" style="width: {{ $pct }}%"></div></div>
                                    <span class="text-xs text-slate-500 tabular-nums">{{ $competition->published_count }}/{{ $competition->evaluations_count }}</span>
                                </div>
                            @else
                                <span class="text-xs text-slate-400">Sin pasadas</span>
                            @endif
                        </td>
                        <td><x-badge :status="$competition->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="trophy" title="Todavía no hay competencias" description="Primero cargá los ejercicios con sus criterios; después creá la competencia." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
