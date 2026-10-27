<div @if ($competition->isInProgress()) wire:poll.10s.visible @endif>
    <x-page-header :title="'Resultados · '.$competition->name" subtitle="Empates: mismo puesto para las participantes con igual puntaje (sin desempate).">
        <x-slot:breadcrumb>
            <a href="{{ route('admin.competitions.index') }}" wire:navigate class="hover:text-brand-700">Competencias</a> /
            @can('competencias.gestionar')
                <a href="{{ route('admin.competitions.show', $competition) }}" wire:navigate class="hover:text-brand-700">{{ $competition->name }}</a> /
            @endcan
        </x-slot:breadcrumb>
        <x-slot:actions>
            <a href="{{ route('admin.competitions.results.csv', $competition) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> Excel (CSV)</a>
            <a href="{{ route('admin.competitions.results.print', $competition) }}" target="_blank" class="btn-secondary"><x-icon name="printer" class="size-4" /> Imprimir / PDF</a>
        </x-slot:actions>
    </x-page-header>

    @if ($categories->count() > 1)
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ($categories as $cat)
                <button type="button" wire:click="$set('category', '{{ $cat['key'] }}')" @class([
                    'rounded-full px-4 py-1.5 text-sm font-medium',
                    'bg-brand-700 text-white' => $selected['key'] === $cat['key'],
                    'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' => $selected['key'] !== $cat['key'],
                ])>{{ $cat['label'] }}</button>
            @endforeach
        </div>
    @endif

    @include('competitions.partials.results-table', ['rows' => $rows, 'exercises' => $competition->exercises])
</div>
