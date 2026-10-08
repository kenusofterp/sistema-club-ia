<div class="mx-auto max-w-2xl">
    <div class="mb-4 flex items-center gap-3">
        <a href="{{ route('admin.lessons.today') }}" wire:navigate class="btn-ghost px-2" aria-label="Volver"><x-icon name="arrow-left" class="size-5" /></a>
        <h1 class="font-display text-xl font-bold text-slate-900">Cobrar en efectivo</h1>
    </div>

    @if ($member)
        <div class="card p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-semibold text-slate-900">{{ $member->fullName() }}</p>
                    <p class="truncate text-sm text-slate-500">{{ $member->activities->pluck('name')->implode(', ') }}</p>
                </div>
                <button type="button" wire:click="clear" class="text-sm font-medium text-brand-700">Cambiar</button>
            </div>

            @if ($fees->isEmpty())
                <p class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">No tiene cuotas pendientes.</p>
            @else
                <ul class="mt-4 divide-y divide-slate-100 rounded-lg ring-1 ring-slate-200">
                    @foreach ($fees as $fee)
                        <li>
                            <label class="flex items-center gap-3 px-3 py-3 text-sm">
                                <input type="checkbox" value="{{ $fee->id }}" wire:model.live="feeIds" class="size-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-slate-800">{{ $fee->concept }}</span>
                                    <span class="block text-xs text-slate-500">Vence {{ $fee->due_date->format('d/m/Y') }}</span>
                                </span>
                                <span class="tabular-nums font-medium text-slate-900">{{ money($fee->balance()) }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
                @error('feeIds')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

                <label for="amount" class="form-label mt-4">Importe recibido</label>
                <input id="amount" type="number" inputmode="decimal" step="0.01" min="0" wire:model="amount" class="form-input text-lg">
                @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-500">Si recibís menos, se imputa primero a la cuota más antigua.</p>

                <button type="button" wire:click="collect" wire:loading.attr="disabled" class="btn-primary mt-4 w-full py-3 text-base"><x-icon name="check" class="size-5" /> Registrar cobro</button>
            @endif
        </div>
    @else
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
            <input wire:model.live.debounce.300ms="search" class="form-input py-3 pl-10" placeholder="Buscar alumno por nombre o documento…" aria-label="Buscar alumno" autocomplete="off">
        </div>

        <ul class="card mt-3 divide-y divide-slate-100">
            @if ($results->isEmpty() && $withDebt->isNotEmpty())
                <li class="px-4 py-2 text-xs font-medium tracking-wide text-slate-400 uppercase">Alumnos con cuotas pendientes</li>
            @endif
            @foreach ($results->isNotEmpty() ? $results : $withDebt as $item)
                <li>
                    <button type="button" wire:click="select({{ $item->id }})" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-slate-50">
                        <span class="min-w-0 truncate font-medium text-slate-800">{{ $item->sortableName() }}</span>
                        <x-icon name="chevron-right" class="size-5 shrink-0 text-slate-400" />
                    </button>
                </li>
            @endforeach
            @if ($results->isEmpty() && $withDebt->isEmpty())
                <li class="px-4 py-6 text-center text-sm text-slate-500">{{ mb_strlen(trim($search)) >= 2 ? 'No se encontraron alumnos de tus grupos.' : 'Ninguno de tus alumnos tiene cuotas pendientes.' }}</li>
            @endif
        </ul>
    @endif
</div>
