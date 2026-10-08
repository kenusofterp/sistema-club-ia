<div>
    <x-page-header title="Registrar pago">
        <x-slot:breadcrumb><a href="{{ route('admin.payments.index') }}" wire:navigate class="hover:text-brand-700">Pagos</a> /</x-slot:breadcrumb>
    </x-page-header>

    <form wire:submit="save" class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card p-6">
                <x-field label="Socio" for="memberSearch" error="memberId" required>
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-slate-400" />
                        <input id="memberSearch" wire:model.live.debounce.300ms="memberSearch" class="form-input pl-9" placeholder="Nombre, documento o N° de socio" autocomplete="off" autofocus>
                        @if ($results->isNotEmpty())
                            <ul class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-slate-200">
                                @foreach ($results as $result)
                                    <li><button type="button" wire:click="selectMember({{ $result->id }})" class="flex w-full justify-between px-3 py-2 text-left text-sm hover:bg-slate-50"><span>{{ $result->sortableName() }}</span><span class="text-slate-400">N° {{ $result->member_number }} · {{ $result->document_number }}</span></button></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </x-field>
                @if ($member)
                    <div class="mt-4 flex items-center gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                        <span class="grid size-10 place-items-center rounded-full bg-brand-100 font-semibold text-brand-700">{{ $member->initials() }}</span>
                        <div class="flex-1">
                            <p class="font-medium text-slate-900">{{ $member->fullName() }}</p>
                            <p class="text-slate-500">N° {{ $member->member_number }} · {{ $member->category->name }}</p>
                        </div>
                        <x-badge :status="$member->status" />
                    </div>
                @endif
            </div>

            @if ($member)
                <div class="card">
                    <div class="border-b border-slate-100 px-5 py-3">
                        <h2 class="font-semibold text-slate-900">Cargos pendientes</h2>
                        <p class="text-xs text-slate-500">El pago se imputa a los seleccionados, del vencimiento más antiguo al más nuevo.</p>
                    </div>
                    @error('selected') <p class="form-error px-5 pt-3">{{ $message }}</p> @enderror
                    <ul class="divide-y divide-slate-100">
                        @forelse ($fees as $fee)
                            <li wire:key="fee-{{ $fee->id }}">
                                <label class="flex cursor-pointer items-center gap-4 px-5 py-3 hover:bg-slate-50">
                                    <input type="checkbox" value="{{ $fee->id }}" wire:model.live="selected" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-slate-800">{{ $fee->concept }}</p>
                                        <p class="text-xs text-slate-500">Vence {{ $fee->due_date->format('d/m/Y') }}@if ((float) $fee->surcharge > 0) · incluye recargo {{ money($fee->surcharge) }}@endif @if ((float) $fee->paid_amount > 0) · pagado {{ money($fee->paid_amount) }}@endif</p>
                                    </div>
                                    <x-badge :status="$fee->status" />
                                    <span class="w-28 text-right text-sm font-semibold tabular-nums">{{ money($fee->balance()) }}</span>
                                </label>
                            </li>
                        @empty
                            <li><x-empty-state icon="check-circle" title="El socio no tiene deuda" description="Para cobrar otro concepto, agregá un cargo desde su ficha." /></li>
                        @endforelse
                    </ul>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card grid gap-4 p-6">
                <x-field label="Importe" for="amount" error="amount" required help="Podés registrar un pago parcial.">
                    <input id="amount" type="number" step="0.01" min="0" wire:model="amount" class="form-input text-lg font-semibold">
                </x-field>
                <x-field label="Medio de pago" for="method" error="method" required>
                    <select id="method" wire:model="method" class="form-input">
                        @foreach ($methods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Fecha" for="payment_date" error="payment_date" required>
                    <input id="payment_date" type="date" wire:model="payment_date" max="{{ today()->toDateString() }}" class="form-input">
                </x-field>
                <x-field label="Referencia / N° de operación" for="reference" error="reference">
                    <input id="reference" wire:model="reference" class="form-input">
                </x-field>
                <x-field label="Observaciones" for="notes" error="notes">
                    <textarea id="notes" wire:model="notes" rows="2" class="form-input"></textarea>
                </x-field>
                <button type="submit" class="btn-primary w-full py-2.5" @disabled(! $member || $fees->isEmpty()) wire:loading.attr="disabled">
                    <x-icon name="check" class="size-4" /> Confirmar pago
                </button>
            </div>
        </div>
    </form>
</div>
