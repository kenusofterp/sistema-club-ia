<div>
    <x-page-header title="Packs de clases" subtitle="Planes del profesor: precio, cantidad de clases y vigencia">
        <x-slot:breadcrumb><a href="{{ route('admin.lessons') }}" wire:navigate class="hover:text-slate-700">Agenda de clases</a> ›</x-slot:breadcrumb>
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Nuevo pack</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-6 flex flex-wrap items-center gap-3 p-4">
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <span>Club</span>
            <select wire:model.live="organizationId" class="form-input w-auto py-1.5">
                @foreach ($organizationOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        @if ($instructorOptions->isNotEmpty())
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <span>Profesor</span>
                <select wire:model.live="instructorId" class="form-input w-auto py-1.5">
                    @foreach ($instructorOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <form wire:submit="saveOwnPrice" class="flex flex-wrap items-center gap-2 text-sm text-slate-600 sm:ml-auto">
            <label for="ownPrice">Mi precio de clase suelta</label>
            <input id="ownPrice" type="number" step="0.01" min="0" wire:model="ownPrice" class="form-input w-32 py-1.5" placeholder="El del club">
            <button type="submit" class="btn-secondary btn-sm">Guardar</button>
            @error('ownPrice')<span class="w-full text-xs text-red-600">{{ $message }}</span>@enderror
        </form>
    </div>
    <p class="-mt-4 mb-6 text-xs text-slate-500">Cada pack se consume con las clases que el profesor da en este club. Sin pack (o sin clases disponibles), el alumno paga la clase suelta.</p>

    <div class="mb-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($packs as $pack)
            <div class="card flex flex-col p-5" wire:key="pack-{{ $pack->id }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ $pack->name }}</h3>
                        <p class="text-sm text-slate-500">Vigencia: {{ $pack->durationLabel() }}</p>
                    </div>
                    <x-badge :color="$pack->is_active ? 'green' : 'gray'">{{ $pack->is_active ? 'Activo' : 'Inactivo' }}</x-badge>
                </div>
                <p class="mt-3 font-display text-2xl font-bold text-slate-900">{{ money($pack->price) }}</p>
                <p class="mt-1 text-sm text-slate-600">
                    {{ $pack->visit_limit ? $pack->visit_limit.' '.($pack->visit_limit === 1 ? 'clase' : 'clases').' '.(\App\Models\Plan::VISIT_PERIODS[$pack->visit_period] ?? '') : 'Clases ilimitadas' }}
                </p>
                <div class="mt-auto flex justify-end gap-1 border-t border-slate-100 pt-3">
                    <button type="button" wire:click="edit({{ $pack->id }})" class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> Editar</button>
                    @if ($pack->is_active)
                        <button type="button" wire:click="sell({{ $pack->id }})" class="btn-secondary btn-sm"><x-icon name="user-plus" class="size-4" /> Asignar a alumno</button>
                    @endif
                </div>
            </div>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty-state icon="id-card" title="Todavía no hay packs" description="Creá packs como «4 clases por mes», «8 clases por mes» o «Clase de prueba»." /></div>
        @endforelse
    </div>

    <div class="card">
        <div class="border-b border-slate-100 px-5 py-3">
            <h2 class="font-semibold text-slate-900">Alumnos con pack vigente</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-2 font-medium">Alumno</th>
                        <th class="px-5 py-2 font-medium">Pack</th>
                        <th class="px-5 py-2 font-medium">Vigencia</th>
                        <th class="px-5 py-2 font-medium">Clases restantes</th>
                        <th class="px-5 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($subscriptions as $subscription)
                        @php($remaining = $subscription->visitsRemaining())
                        <tr wire:key="sub-{{ $subscription->id }}">
                            <td class="px-5 py-2 font-medium text-slate-800">{{ $subscription->member->fullName() }}</td>
                            <td class="px-5 py-2 text-slate-600">{{ $subscription->plan->name }}</td>
                            <td class="px-5 py-2 text-slate-600">{{ $subscription->periodLabel() }}</td>
                            <td class="px-5 py-2">
                                @if ($remaining === null)
                                    <span class="text-slate-500">Ilimitadas</span>
                                @else
                                    <span @class(['font-semibold', 'text-red-600' => $remaining === 0, 'text-slate-900' => $remaining > 0])>{{ $remaining }}</span>
                                    <span class="text-slate-400">/ {{ $subscription->plan->visit_limit }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-2 text-right">
                                <button type="button" wire:click="cancelSubscription({{ $subscription->id }})" wire:confirm="¿Cancelar el pack de {{ $subscription->member->fullName() }}? Si el cargo no está pago, se anula." class="text-xs font-medium text-red-600 hover:underline">Cancelar</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-6 text-center text-slate-500">Ningún alumno tiene un pack vigente en este club.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar pack' : 'Nuevo pack'" max-width="max-w-xl">
        <form wire:submit="save" id="pack-form" class="grid gap-4 sm:grid-cols-2">
            <x-field label="Nombre" for="name" error="name" required class="sm:col-span-2">
                <input id="name" wire:model="name" class="form-input" placeholder="Ej.: 8 clases por mes">
            </x-field>
            <x-field label="Precio" for="price" error="price" required>
                <input id="price" type="number" step="0.01" min="0" wire:model="price" class="form-input">
            </x-field>
            <x-field label="Vigencia" for="duration_value" error="duration_value" required>
                <div class="flex gap-2">
                    <input id="duration_value" type="number" min="1" wire:model="duration_value" class="form-input w-20">
                    <select wire:model="duration_unit" class="form-input" aria-label="Unidad">
                        @foreach (\App\Models\Plan::DURATION_UNITS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </x-field>
            <x-field label="Cantidad de clases" for="visit_limit" error="visit_limit">
                <input id="visit_limit" type="number" min="1" wire:model="visit_limit" class="form-input" placeholder="Vacío = ilimitadas">
            </x-field>
            <x-field label="Se cuentan" for="visit_period" error="visit_period">
                <select id="visit_period" wire:model="visit_period" class="form-input">
                    @foreach (\App\Models\Plan::VISIT_PERIODS as $value => $label)
                        <option value="{{ $value }}">{{ ucfirst($label) }}</option>
                    @endforeach
                </select>
            </x-field>
            <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Activo (se puede asignar)
            </label>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="pack-form" class="btn-primary">Guardar</button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showSell" title="Asignar pack" max-width="max-w-lg">
        @if ($sellPlan)
            <div class="grid gap-4">
                <p class="text-sm text-slate-600"><strong class="text-slate-900">{{ $sellPlan->name }}</strong> · {{ money($sellPlan->price) }} · {{ $sellPlan->durationLabel() }}</p>
                <x-field label="Alumno" for="sellSearch" error="sellMemberId" required>
                    @if ($sellMemberId)
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                            <span class="font-medium text-slate-800">{{ $sellMemberName }}</span>
                            <button type="button" wire:click="$set('sellMemberId', null)" class="text-xs font-medium text-brand-700 hover:underline">Cambiar</button>
                        </div>
                    @else
                        <div class="relative">
                            <input id="sellSearch" wire:model.live.debounce.300ms="sellSearch" class="form-input" placeholder="Nombre o documento…" autocomplete="off">
                            @if ($sellResults->isNotEmpty())
                                <ul class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-slate-200">
                                    @foreach ($sellResults as $result)
                                        <li><button type="button" wire:click="selectStudent({{ $result['id'] }})" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50">{{ $result['name'] }} <span class="text-slate-400">· {{ $result['detail'] }}</span></button></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                </x-field>
                <x-field label="Comienza" for="sellStart" error="sellStart" required>
                    <input id="sellStart" type="date" wire:model="sellStart" class="form-input">
                </x-field>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="sellAutoRenew" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Renovar automáticamente al vencer
                </label>
                <p class="rounded-lg bg-sky-50 p-3 text-xs text-sky-800">El pack queda activo de inmediato y el cargo se registra en la cuenta del alumno, a nombre del profesor.</p>
            </div>
        @endif
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="button" wire:click="confirmSale" class="btn-primary">Asignar</button>
        </x-slot:footer>
    </x-modal>
</div>
