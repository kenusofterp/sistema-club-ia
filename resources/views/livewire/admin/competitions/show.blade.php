<div>
    <x-page-header :title="$competition->name" :subtitle="$competition->date->format('d/m/Y').($competition->location ? ' · '.$competition->location : '')">
        <x-slot:breadcrumb><a href="{{ route('admin.competitions.index') }}" wire:navigate class="hover:text-brand-700">Competencias</a> /</x-slot:breadcrumb>
        <x-slot:actions>
            <x-badge :status="$competition->status" />
            @can('competencias.resultados')
                <a href="{{ route('admin.competitions.results', $competition) }}" wire:navigate class="btn-secondary"><x-icon name="chart" class="size-4" /> Resultados</a>
            @endcan
            @unless ($competition->isFinished())
                <a href="{{ route('admin.competitions.edit', $competition) }}" wire:navigate class="btn-secondary"><x-icon name="pencil" class="size-4" /> Editar</a>
            @endunless
            @if ($competition->isDraft())
                <button type="button" wire:click="start" wire:confirm="¿Iniciar la competencia? Se congelan los criterios de cada ejercicio y los jueces pueden empezar a cargar notas." class="btn-primary"><x-icon name="flag" class="size-4" /> Iniciar competencia</button>
            @elseif ($competition->isInProgress())
                <button type="button" wire:click="finish" wire:confirm="¿Finalizar la competencia? Ya no se podrán cargar ni modificar notas." class="btn-secondary">Finalizar</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Participantes" :value="$stats['entries']" icon="users" />
        <x-stat-card label="Puestos" :value="$stats['stations']" icon="clipboard" />
        <x-stat-card label="Notas publicadas" :value="$stats['published']" icon="check-circle" />
        <x-stat-card label="Pasadas pendientes" :value="$stats['pending']" icon="clock" />
    </div>

    @if ($competition->isDraft())
        <div class="mb-6 rounded-lg bg-sky-50 p-4 text-sm text-sky-800">
            <strong>Para iniciar:</strong> cada puesto necesita sus 3 jueces y cada ejercicio al menos un puesto. Podés inscribir participantes y armar las colas antes o durante la competencia.
        </div>
    @endif

    <div class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200 text-sm">
        @foreach (['puestos' => 'Puestos y jueces', 'participantes' => 'Participantes', 'colas' => 'Colas de pasada', 'pantallas' => 'Pantallas'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                '-mb-px border-b-2 px-4 py-2 font-medium whitespace-nowrap',
                'border-brand-600 text-brand-700' => $tab === $key,
                'border-transparent text-slate-500 hover:text-slate-700' => $tab !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'puestos')
        <livewire:admin.competitions.stations :competition="$competition" :key="'stations-'.$competition->id" />
    @elseif ($tab === 'participantes')
        <livewire:admin.competitions.entries :competition="$competition" :key="'entries-'.$competition->id" />
    @elseif ($tab === 'colas')
        <livewire:admin.competitions.queues :competition="$competition" :key="'queues-'.$competition->id" />
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="card p-5">
                <h2 class="font-semibold text-slate-900">Pantalla general (ranking)</h2>
                <p class="mt-1 text-sm text-slate-500">Top 12 por nivel/grupo en vivo. Rota entre categorías cada 20 segundos; con <code>?nivel=</code> se fija una.</p>
                @php($general = route('display.ranking', $competition->display_token))
                <div class="mt-3 flex items-center gap-2">
                    <input readonly value="{{ $general }}" class="form-input text-xs" aria-label="Enlace de la pantalla general" x-on:focus="$el.select()">
                    <a href="{{ $general }}" target="_blank" class="btn-secondary btn-sm"><x-icon name="external" class="size-4" /> Abrir</a>
                </div>
            </div>
            <div class="card p-5">
                <h2 class="font-semibold text-slate-900">Pantalla de cada puesto</h2>
                <p class="mt-1 text-sm text-slate-500">Participante actual, notas de los jueces y puntaje apenas se publica.</p>
                <ul class="mt-3 space-y-2">
                    @forelse ($competition->stations as $station)
                        @php($url = route('display.station', $station->display_token))
                        <li class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 p-3 text-sm">
                            <span class="font-medium text-slate-800">{{ $station->name }}</span>
                            <a href="{{ $url }}" target="_blank" class="btn-secondary btn-sm"><x-icon name="external" class="size-4" /> Abrir</a>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">Todavía no hay puestos.</li>
                    @endforelse
                </ul>
            </div>
            <p class="text-xs text-slate-500 lg:col-span-2">Las pantallas no piden usuario: cualquiera con el enlace puede verlas (solo lectura). No compartas los enlaces fuera del torneo.</p>
        </div>
    @endif

    @if ($competition->isDraft())
        <div class="mt-8 text-right">
            <button type="button" wire:click="delete" wire:confirm="¿Eliminar la competencia en borrador?" class="btn-ghost text-red-600"><x-icon name="trash" class="size-4" /> Eliminar competencia</button>
        </div>
    @endif
</div>
