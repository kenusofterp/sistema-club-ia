<div>
    <x-page-header title="Nuevo adicional" subtitle="Vestimenta o artículo que se cobra a los alumnos elegidos (ej. la malla de un torneo).">
        <x-slot:breadcrumb><a href="{{ route('admin.extra-charges.index') }}" wire:navigate class="hover:text-brand-700">Vestimenta y artículos</a> /</x-slot:breadcrumb>
    </x-page-header>

    <form wire:submit="save" class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card grid gap-5 p-6 md:grid-cols-2">
                <x-field label="Nombre" for="name" error="name" required>
                    <input id="name" wire:model="name" class="form-input" placeholder="ej. Malla">
                </x-field>
                <x-field label="Precio" for="price" error="price" required>
                    <input id="price" type="number" step="0.01" min="0" wire:model="price" class="form-input">
                </x-field>
                <x-field label="Pagar hasta" for="due_date" error="due_date" required :help="$maxDueDate ? 'A más tardar el día del torneo ('.\Illuminate\Support\Carbon::parse($maxDueDate)->format('d/m').').' : null">
                    <input id="due_date" type="date" wire:model="due_date" @if ($maxDueDate) max="{{ $maxDueDate }}" @endif class="form-input">
                </x-field>
                <x-field label="Detalle (opcional)" for="description" error="description">
                    <input id="description" wire:model="description" class="form-input" placeholder="ej. talle, color">
                </x-field>
            </div>

            <div class="card">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4">
                    <div>
                        <h2 class="font-semibold text-slate-900">¿A quiénes se les cobra?</h2>
                        <p class="text-sm text-slate-500"><strong class="text-slate-900">{{ count($selected) }}</strong> tildados de {{ $candidateCount }}</p>
                    </div>
                    <span class="flex gap-3 text-sm">
                        <button type="button" wire:click="selectAll" class="font-medium text-brand-700">Todos</button>
                        <button type="button" wire:click="selectNone" class="font-medium text-slate-500">Ninguno</button>
                    </span>
                </div>
                @error('selected') <p class="form-error px-4 pt-3">{{ $message }}</p> @enderror
                @if ($candidateCount > 12)
                    <div class="p-4 pb-0"><input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Buscar alumno…"></div>
                @endif
                <ul class="max-h-[28rem] divide-y divide-slate-100 overflow-y-auto">
                    @forelse ($candidates as $member)
                        @php($checked = in_array((string) $member->id, $selected, true))
                        <li wire:key="c-{{ $member->id }}">
                            <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5">
                                <input type="checkbox" value="{{ $member->id }}" wire:model.live="selected" class="size-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span @class(['flex-1 text-sm', 'text-slate-800' => $checked, 'text-slate-400' => ! $checked])>{{ $member->sortableName() }}</span>
                            </label>
                        </li>
                    @empty
                        <li class="p-6 text-center text-sm text-slate-500">
                            {{ $origin === 'torneo' ? ($tournamentId ? 'El torneo no tiene participantes todavía.' : 'Elegí el torneo.') : ($origin === 'nivel' && ! $activityId ? 'Elegí el '.mb_strtolower(activity_label()).'.' : 'No hay alumnos.') }}
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card space-y-4 p-6">
                <fieldset class="space-y-2">
                    <legend class="form-label">¿Para qué es?</legend>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model.live="origin" value="torneo" class="border-slate-300 text-brand-600 focus:ring-brand-500"> Un torneo</label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model.live="origin" value="nivel" class="border-slate-300 text-brand-600 focus:ring-brand-500"> Un {{ mb_strtolower(activity_label()) }}</label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model.live="origin" value="alumnos" class="border-slate-300 text-brand-600 focus:ring-brand-500"> Alumnos que elijo</label>
                </fieldset>

                @if ($origin === 'torneo')
                    <x-field label="Torneo" for="tournamentId" error="tournamentId" required>
                        <select id="tournamentId" wire:model.live="tournamentId" class="form-input">
                            <option value="">Elegí…</option>
                            @foreach ($tournaments as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->startsOn()?->format('d/m') }})</option>
                            @endforeach
                        </select>
                    </x-field>
                @elseif ($origin === 'nivel')
                    <x-field :label="activity_label()" for="activityId" error="activityId" required>
                        <select id="activityId" wire:model.live="activityId" class="form-input">
                            <option value="">Elegí…</option>
                            @foreach ($activities as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
                <p class="text-xs text-slate-500">A cada alumno tildado se le suma el cargo a su cuenta. Se paga como la cuota (efectivo, transferencia o secretaría) y, si alguien ya lo consiguió, se lo anulás después desde el detalle.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ $tournamentId ? route('admin.tournaments.show', $tournamentId) : route('admin.extra-charges.index') }}" wire:navigate class="btn-secondary flex-1">Cancelar</a>
                <button type="submit" class="btn-primary flex-1"><x-icon name="check" class="size-4" /> Cargar</button>
            </div>
        </div>
    </form>
</div>
