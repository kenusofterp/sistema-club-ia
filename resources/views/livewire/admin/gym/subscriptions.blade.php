<div>
    <x-page-header title="Planes de socios" subtitle="Membresías contratadas, vencimientos y renovaciones">
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Asignar plan</button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Planes vigentes" :value="$stats['active']" icon="id-card" />
        <x-stat-card label="Pendientes de pago" :value="$stats['pending']" icon="banknotes" tone="amber" />
        <button type="button" wire:click="$set('expiring', true)" class="text-left"><x-stat-card label="Vencen en 7 días" :value="$stats['expiring']" icon="clock" tone="red" /></button>
    </div>

    <div class="card">
        <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-4">
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Buscar socio…" class="form-input">
            <select wire:model.live="status" class="form-input">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="plan" class="form-input">
                <option value="">Todos los planes</option>
                @foreach ($plans as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model.live="expiring" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Solo por vencer (7 días)</label>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Socio</th><th>Plan</th><th>Vigencia</th><th class="text-right">Precio</th><th>Estado</th><th>Renov. autom.</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($subscriptions as $s)
                        <tr wire:key="sub-{{ $s->id }}">
                            <td><a href="{{ route('admin.members.show', ['member' => $s->member, 'tab' => 'planes']) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $s->member->sortableName() }}</a></td>
                            <td>{{ $s->plan->name }}</td>
                            <td class="whitespace-nowrap">
                                {{ $s->periodLabel() }}
                                @if ($s->status === \App\Enums\SubscriptionStatus::Active && $s->end_date->gte(today()))
                                    <span @class(['block text-xs', 'text-red-600 font-medium' => $s->daysLeft() <= 7, 'text-slate-400' => $s->daysLeft() > 7])>{{ $s->daysLeft() }} días restantes</span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums">{{ money($s->price) }}</td>
                            <td>
                                <x-badge :status="$s->status" />
                                @if ($s->renewal)<span class="block text-xs text-slate-400">Renovado</span>@endif
                            </td>
                            <td>
                                @if (in_array($s->status, [\App\Enums\SubscriptionStatus::Active, \App\Enums\SubscriptionStatus::Pending]))
                                    <button type="button" wire:click="toggleAutoRenew({{ $s->id }})" @class(['btn btn-sm', 'bg-emerald-50 text-emerald-700' => $s->auto_renew, 'bg-slate-100 text-slate-500' => ! $s->auto_renew])>{{ $s->auto_renew ? 'Sí' : 'No' }}</button>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @if (! $s->renewal && in_array($s->status, [\App\Enums\SubscriptionStatus::Active, \App\Enums\SubscriptionStatus::Expired]))
                                    <button type="button" wire:click="renew({{ $s->id }})" wire:confirm="¿Generar ahora el siguiente período de {{ $s->plan->name }}?" class="btn-ghost btn-sm" title="Renovar"><x-icon name="refresh" class="size-4" /></button>
                                @endif
                                @if ($s->status === \App\Enums\SubscriptionStatus::Pending)
                                    @can('pagos.registrar')
                                        <a href="{{ route('admin.payments.create', ['socio' => $s->member_id]) }}" wire:navigate class="btn-ghost btn-sm" title="Cobrar"><x-icon name="banknotes" class="size-4" /></a>
                                    @endcan
                                @endif
                                @if (in_array($s->status, [\App\Enums\SubscriptionStatus::Active, \App\Enums\SubscriptionStatus::Pending]))
                                    <button type="button" wire:click="confirmCancel({{ $s->id }})" class="btn-ghost btn-sm text-red-600" title="Cancelar"><x-icon name="ban" class="size-4" /></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="id-card" title="Sin planes para los filtros elegidos" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($subscriptions->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $subscriptions->links() }}</div>@endif
    </div>

    <x-modal wire:model="showForm" title="Asignar plan" max-width="max-w-lg">
        <div class="grid gap-4">
            <x-field label="Socio" for="memberSearch" error="memberId" required>
                <div class="relative">
                    <input id="memberSearch" wire:model.live.debounce.300ms="memberSearch" class="form-input" placeholder="Buscar socio activo…" autocomplete="off">
                    @if ($memberResults->isNotEmpty())
                        <ul class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-slate-200">
                            @foreach ($memberResults as $result)
                                <li><button type="button" wire:click="selectMember({{ $result->id }})" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50">{{ $result->sortableName() }} <span class="text-slate-400">· N° {{ $result->member_number }}</span></button></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-field>
            <x-field label="Plan" for="planId" error="planId" required>
                <select id="planId" wire:model="planId" class="form-input">
                    <option value="">Seleccionar…</option>
                    @foreach ($plans->where('is_active', true) as $item)
                        <option value="{{ $item->id }}">{{ $item->name }} — {{ money($item->price) }} / {{ $item->durationLabel() }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Comienza" for="startDate" error="startDate" required>
                <input id="startDate" type="date" wire:model="startDate" min="{{ today()->toDateString() }}" class="form-input">
            </x-field>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="autoRenew" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Renovar automáticamente al vencer</label>
            @if (setting('gym.activate_on_payment', true))
                <p class="rounded-lg bg-sky-50 p-3 text-xs text-sky-800">Se generará el cargo en la cuenta del socio y el plan se activará al registrarse el pago.</p>
            @endif
        </div>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="button" wire:click="subscribe" class="btn-primary">Asignar plan</button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showCancel" title="Cancelar plan" max-width="max-w-md">
        <p class="mb-4 text-sm text-slate-600">Se anula el cargo si no tiene pagos y se desactiva la renovación automática.</p>
        <x-field label="Motivo" for="cancelReason" error="cancelReason" required>
            <textarea id="cancelReason" wire:model="cancelReason" rows="3" class="form-input"></textarea>
        </x-field>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Volver</button>
            <button type="button" wire:click="cancel" class="btn-danger">Cancelar plan</button>
        </x-slot:footer>
    </x-modal>
</div>
