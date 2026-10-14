<div>
    <x-page-header title="Vestimenta y artículos" subtitle="Lo que el profe compra y cobra a los alumnos que elige: la malla de un torneo, la remera del grupo…">
        <x-slot:actions>
            <a href="{{ route('admin.extra-charges.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" class="size-4" /> Nuevo adicional</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 max-w-md">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar…" class="form-input">
    </div>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Artículo</th><th>Para</th><th class="text-right">Precio</th><th>Pagar hasta</th><th class="text-right">Alumnos</th><th class="text-right">Deben</th><th class="text-right">Cobrado</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($charges as $charge)
                        <tr wire:key="x-{{ $charge->id }}">
                            <td><a href="{{ route('admin.extra-charges.show', $charge) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $charge->name }}</a></td>
                            <td class="text-sm text-slate-600">{{ $charge->contextLabel() }}</td>
                            <td class="text-right tabular-nums">{{ money($charge->price) }}</td>
                            <td>{{ $charge->due_date->format('d/m/Y') }}</td>
                            <td class="text-right tabular-nums">{{ $charge->charged_count }}</td>
                            <td @class(['text-right tabular-nums', 'font-semibold text-red-600' => $charge->debtors_count > 0])>
                                @if ($charge->debtors_count > 0)
                                    <a href="{{ route('admin.extra-charges.show', ['extraCharge' => $charge, 'ver' => 'deben']) }}" wire:navigate class="hover:underline">{{ $charge->debtors_count }}</a>
                                @else
                                    0
                                @endif
                            </td>
                            <td class="text-right tabular-nums">{{ money($charge->paid_total ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="tag" title="Todavía no cargaste vestimenta ni artículos" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
