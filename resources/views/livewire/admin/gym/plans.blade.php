<div>
    <x-page-header title="Planes" subtitle="Membresías del gimnasio: precio, duración, acceso, horarios y visitas">
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Nuevo plan</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($plans as $plan)
            <div @class(['card flex flex-col p-5', 'ring-2 ring-accent-500' => $plan->is_featured]) wire:key="plan-{{ $plan->id }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ $plan->name }}</h3>
                        <p class="text-sm text-slate-500">{{ $plan->durationLabel() }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <x-badge :color="$plan->is_active ? 'green' : 'gray'">{{ $plan->is_active ? 'Activo' : 'Inactivo' }}</x-badge>
                        @if ($plan->is_featured)<x-badge color="yellow">Destacado</x-badge>@endif
                    </div>
                </div>
                <p class="mt-3 font-display text-2xl font-bold text-slate-900">{{ money($plan->price) }}</p>
                <ul class="mt-3 space-y-1.5 text-sm text-slate-600">
                    <li class="flex gap-2"><x-icon name="key" class="size-4 shrink-0 text-brand-600" /> {{ $plan->access_type->label() }}</li>
                    <li class="flex gap-2"><x-icon name="refresh" class="size-4 shrink-0 text-brand-600" /> {{ $plan->visitLimitLabel() }}</li>
                    @forelse ($plan->windowsLabels() as $label)
                        <li class="flex gap-2"><x-icon name="clock" class="size-4 shrink-0 text-brand-600" /> {{ $label }}</li>
                    @empty
                        <li class="flex gap-2"><x-icon name="clock" class="size-4 shrink-0 text-brand-600" /> Todo el horario</li>
                    @endforelse
                    @if ($plan->activities->isNotEmpty())
                        <li class="flex gap-2"><x-icon name="trophy" class="size-4 shrink-0 text-brand-600" /> {{ $plan->activities->pluck('name')->implode(', ') }}</li>
                    @endif
                </ul>
                <p class="mt-3 text-xs text-slate-500">{{ $plan->active_count }} socios con el plan vigente @unless ($plan->is_public) · <span class="text-amber-700">no visible en la web</span>@endunless</p>
                <div class="mt-auto flex justify-end gap-1 border-t border-slate-100 pt-3">
                    <a href="{{ route('admin.gym.subscriptions', ['plan' => $plan->id]) }}" wire:navigate class="btn-ghost btn-sm"><x-icon name="users" class="size-4" /> Socios</a>
                    <button type="button" wire:click="edit({{ $plan->id }})" class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> Editar</button>
                    <button type="button" wire:click="delete({{ $plan->id }})" wire:confirm="¿Eliminar el plan {{ $plan->name }}?" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button>
                </div>
            </div>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty-state icon="id-card" title="No hay planes" description="Creá planes como «Musculación libre», «Funcional 3 veces por semana» o «Pase mañana»." /></div>
        @endforelse
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar plan' : 'Nuevo plan'" max-width="max-w-3xl">
        <form wire:submit="save" id="plan-form" class="grid gap-4 sm:grid-cols-2">
            <x-field label="Nombre" for="name" error="name" required>
                <input id="name" wire:model="name" class="form-input" placeholder="Ej.: Musculación libre">
            </x-field>
            <x-field label="Precio" for="price" error="price" required>
                <input id="price" type="number" step="0.01" min="0" wire:model="price" class="form-input">
            </x-field>
            <x-field label="Descripción" for="description" error="description" class="sm:col-span-2">
                <input id="description" wire:model="description" class="form-input">
            </x-field>
            <x-field label="Duración" for="duration_value" error="duration_value" required>
                <div class="flex gap-2">
                    <input id="duration_value" type="number" min="1" wire:model="duration_value" class="form-input w-24">
                    <select wire:model="duration_unit" class="form-input">
                        @foreach (\App\Models\Plan::DURATION_UNITS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </x-field>
            <x-field label="Tipo de acceso" for="access_type" error="access_type" required>
                <select id="access_type" wire:model.live="access_type" class="form-input">
                    @foreach ($accessTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Límite de visitas" for="visit_limit" error="visit_limit" help="Vacío = ilimitadas. Se cuenta un ingreso por día.">
                <div class="flex gap-2">
                    <input id="visit_limit" type="number" min="1" wire:model="visit_limit" class="form-input w-24" placeholder="∞">
                    <select wire:model="visit_period" class="form-input">
                        @foreach (\App\Models\Plan::VISIT_PERIODS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </x-field>
            <x-field label="Orden" for="sort_order" error="sort_order">
                <input id="sort_order" type="number" min="0" wire:model="sort_order" class="form-input">
            </x-field>

            @if (\App\Enums\PlanAccessType::from($access_type)->includesClasses())
                <div class="sm:col-span-2">
                    <span class="form-label">Clases incluidas</span>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach ($activities as $activity)
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <input type="checkbox" value="{{ $activity->id }}" wire:model="activityIds" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> {{ $activity->name }}
                            </label>
                        @endforeach
                    </div>
                    @error('activityIds') <p class="form-error">{{ $message }}</p> @enderror
                    <p class="form-help">Con «solo clases», el socio puede ingresar desde {{ setting('gym.class_checkin_tolerance', 20) }} minutos antes de cada clase hasta que termina.</p>
                </div>
            @endif

            <div class="sm:col-span-2">
                <div class="mb-2 flex items-center justify-between">
                    <span class="form-label mb-0">Franjas horarias permitidas</span>
                    <button type="button" wire:click="addWindow" class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Agregar franja</button>
                </div>
                @forelse ($windows as $i => $window)
                    <div class="mb-2 space-y-2 rounded-lg bg-slate-50 p-3" wire:key="win-{{ $i }}">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (\App\Models\ActivitySchedule::DAYS as $day => $label)
                                <label class="cursor-pointer">
                                    <input type="checkbox" value="{{ $day }}" wire:model="windows.{{ $i }}.days" class="peer sr-only">
                                    <span class="inline-block rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-600 peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white">{{ mb_substr($label, 0, 3) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            de <input type="time" wire:model="windows.{{ $i }}.from" class="form-input w-auto">
                            a <input type="time" wire:model="windows.{{ $i }}.to" class="form-input w-auto">
                            <button type="button" wire:click="removeWindow({{ $i }})" class="btn-ghost ml-auto text-red-600"><x-icon name="trash" class="size-4" /></button>
                        </div>
                        @error("windows.$i.days") <p class="form-error">{{ $message }}</p> @enderror
                        @error("windows.$i.to") <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Sin restricciones: acceso en todo el horario del gimnasio.</p>
                @endforelse
            </div>

            <div class="flex flex-wrap gap-4 sm:col-span-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Activo</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_public" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Visible en la web y el portal</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_featured" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Destacado («el más elegido»)</label>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="plan-form" class="btn-primary">Guardar</button>
        </x-slot:footer>
    </x-modal>
</div>
