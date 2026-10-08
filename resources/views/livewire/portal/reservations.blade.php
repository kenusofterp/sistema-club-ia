<div class="space-y-6">
    <h1 class="font-display text-2xl font-bold text-slate-900">Reservas</h1>

    @if ($mine->isNotEmpty())
        <section>
            <h2 class="mb-3 font-semibold text-slate-900">Mis próximas reservas</h2>
            <div class="space-y-2">
                @foreach ($mine as $reservation)
                    <div class="card flex items-center justify-between gap-3 p-4" wire:key="my-{{ $reservation->id }}">
                        <div>
                            <p class="font-medium text-slate-800">{{ $reservation->facility->name }}</p>
                            <p class="text-sm text-slate-500">{{ ucfirst($reservation->date->translatedFormat('l j \d\e F')) }} · {{ $reservation->timeRange() }}</p>
                        </div>
                        <button type="button" wire:click="cancel({{ $reservation->id }})" wire:confirm="¿Cancelar esta reserva?" class="btn-ghost btn-sm text-red-600">Cancelar</button>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-500">Podés cancelar hasta {{ setting('club.reservation_cancel_hours', 24) }} horas antes.</p>
        </section>
    @endif

    <section class="card p-5">
        <h2 class="mb-4 font-semibold text-slate-900">Nueva reserva</h2>
        @if ($facilities->isEmpty())
            <p class="text-sm text-slate-500">No hay instalaciones disponibles para reservar.</p>
        @else
            <div class="grid gap-3 sm:grid-cols-3">
                <x-field label="Instalación" for="facilityId">
                    <select id="facilityId" wire:model.live="facilityId" class="form-input">
                        @foreach ($facilities as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Fecha" for="date" error="date">
                    <input id="date" type="date" wire:model.live="date" min="{{ today()->toDateString() }}" max="{{ $maxDate }}" class="form-input">
                </x-field>
                <x-field label="Duración" for="slots">
                    <select id="slots" wire:model="slotCount" class="form-input">
                        @for ($i = 1; $i <= ($facility?->max_slots_per_booking ?? 1); $i++)
                            <option value="{{ $i }}">{{ $i * ($facility?->slot_minutes ?? 60) }} minutos</option>
                        @endfor
                    </select>
                </x-field>
            </div>
            @if ($facility && (float) $facility->hourly_rate > 0)
                <p class="mt-3 text-sm text-slate-500">Tarifa: {{ money($facility->hourly_rate) }} por hora. El importe se agrega a tu cuenta.</p>
            @endif

            <div class="mt-5 grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-7" wire:loading.class="opacity-50">
                @foreach ($slotList as $slot)
                    @if ($slot['available'])
                        <button type="button" wire:click="book('{{ $slot['start'] }}')" wire:confirm="¿Reservar {{ $facility->name }} a las {{ $slot['start'] }}?"
                                class="rounded-lg border border-brand-200 bg-brand-50 px-2 py-2.5 text-sm font-semibold text-brand-800 transition hover:bg-brand-600 hover:text-white">{{ $slot['start'] }}</button>
                    @else
                        <span class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-2.5 text-center text-sm text-slate-400 line-through">{{ $slot['start'] }}</span>
                    @endif
                @endforeach
            </div>
        @endif
    </section>
</div>
