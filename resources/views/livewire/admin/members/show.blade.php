<div>
    <x-page-header :title="$member->fullName()">
        <x-slot:breadcrumb><a href="{{ route('admin.members.index') }}" wire:navigate class="hover:text-brand-700">Socios</a> /</x-slot:breadcrumb>
        <x-slot:actions>
            @can('socios.editar')
                @if ($member->status === \App\Enums\MemberStatus::Pending)
                    <button type="button" wire:click="approve" wire:confirm="¿Aprobar la solicitud de {{ $member->fullName() }}?" class="btn-primary"><x-icon name="check" class="size-4" /> Aprobar</button>
                    <button type="button" wire:click="openStatus('reject')" class="btn-secondary">Rechazar</button>
                @endif
                @if (in_array($member->status, [\App\Enums\MemberStatus::Suspended, \App\Enums\MemberStatus::Inactive]))
                    <button type="button" wire:click="reactivate" wire:confirm="¿Reactivar al socio?" class="btn-primary"><x-icon name="refresh" class="size-4" /> Reactivar</button>
                @endif
                <a href="{{ route('admin.members.edit', $member) }}" wire:navigate class="btn-secondary"><x-icon name="pencil" class="size-4" /> Editar</a>
            @endcan
            @can('pagos.registrar')
                @if ($member->status !== \App\Enums\MemberStatus::Pending)
                    <a href="{{ route('admin.payments.create', ['socio' => $member->id]) }}" wire:navigate class="btn-primary"><x-icon name="banknotes" class="size-4" /> Registrar pago</a>
                @endif
            @endcan
            <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                <button type="button" x-on:click="open = !open" class="btn-secondary px-2.5" aria-label="Más acciones"><x-icon name="chevron-down" class="size-4" /></button>
                <div x-show="open" x-cloak x-transition class="absolute right-0 z-20 mt-2 w-60 rounded-xl bg-white py-2 shadow-lg ring-1 ring-slate-900/10">
                    <a href="{{ $member->verificationUrl() }}" target="_blank" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50"><x-icon name="qr" class="size-4" /> Verificar carnet</a>
                    @can('socios.editar')
                        @if ($member->user?->is_active)
                            <button type="button" wire:click="disablePortal" wire:confirm="¿Deshabilitar el acceso al portal?" class="flex w-full items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50"><x-icon name="ban" class="size-4" /> Quitar acceso al portal</button>
                        @elseif ($member->isActive())
                            <button type="button" wire:click="enablePortal" class="flex w-full items-center gap-2 px-4 py-2 text-sm hover:bg-slate-50"><x-icon name="key" class="size-4" /> Habilitar acceso al portal</button>
                        @endif
                        @if ($member->isActive())
                            <button type="button" wire:click="openStatus('suspend')" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-amber-700 hover:bg-amber-50"><x-icon name="warning" class="size-4" /> Suspender</button>
                        @endif
                        @if ($member->status !== \App\Enums\MemberStatus::Inactive && $member->status !== \App\Enums\MemberStatus::Pending)
                            <button type="button" wire:click="openStatus('deactivate')" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50"><x-icon name="logout" class="size-4" /> Dar de baja</button>
                        @endif
                    @endcan
                    @can('socios.eliminar')
                        <button type="button" wire:click="delete" wire:confirm="¿Eliminar definitivamente al socio? Esta acción no se puede deshacer." class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50"><x-icon name="trash" class="size-4" /> Eliminar</button>
                    @endcan
                </div>
            </div>
        </x-slot:actions>
    </x-page-header>

    @if (session('receipt_url'))
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-emerald-50 px-5 py-4 ring-1 ring-emerald-200">
            <p class="flex items-center gap-2 text-sm font-medium text-emerald-800"><x-icon name="check-circle" class="size-5" /> Pago registrado correctamente.</p>
            <a href="{{ session('receipt_url') }}" target="_blank" class="btn-primary btn-sm"><x-icon name="printer" class="size-4" /> Imprimir recibo</a>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-4">
        {{-- Tarjeta de perfil --}}
        <aside class="space-y-6">
            <div class="card p-6 text-center">
                @if ($member->photoUrl())
                    <img src="{{ $member->photoUrl() }}" alt="" class="mx-auto size-24 rounded-full object-cover">
                @else
                    <span class="mx-auto grid size-24 place-items-center rounded-full bg-brand-100 font-display text-3xl font-bold text-brand-700">{{ $member->initials() }}</span>
                @endif
                <p class="mt-4 font-display text-lg font-semibold text-slate-900">{{ $member->fullName() }}</p>
                <p class="text-sm text-slate-500">Socio N° {{ $member->member_number ?? '—' }} · {{ $member->category->name }}</p>
                <div class="mt-3"><x-badge :status="$member->status" /></div>
                <div class="mt-5 rounded-xl {{ bccomp($balance, '0', 2) > 0 ? 'bg-red-50' : 'bg-emerald-50' }} p-4">
                    <p class="text-xs text-slate-500">Saldo adeudado</p>
                    <p class="font-display text-2xl font-bold {{ bccomp($balance, '0', 2) > 0 ? 'text-red-600' : 'text-emerald-700' }} tabular-nums">{{ money($balance) }}</p>
                </div>
            </div>
            <div class="card p-6">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs text-slate-500">Documento</dt><dd class="font-medium">{{ $member->document_type }} {{ $member->document_number }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Nacimiento</dt><dd class="font-medium">{{ $member->birth_date->format('d/m/Y') }} ({{ $member->age() }} años)</dd></div>
                    <div><dt class="text-xs text-slate-500">Correo</dt><dd class="font-medium break-all">{{ $member->email ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Teléfono</dt><dd class="font-medium">{{ $member->phone ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Domicilio</dt><dd class="font-medium">{{ collect([$member->address, $member->city])->filter()->implode(', ') ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Ingreso</dt><dd class="font-medium">{{ $member->admission_date?->format('d/m/Y') ?? '—' }}</dd></div>
                    @if ($member->leave_date)
                        <div><dt class="text-xs text-slate-500">Baja</dt><dd class="font-medium">{{ $member->leave_date->format('d/m/Y') }} — {{ $member->leave_reason }}</dd></div>
                    @endif
                    <div><dt class="text-xs text-slate-500">Portal del socio</dt><dd class="font-medium">{{ $member->user ? ($member->user->is_active ? 'Habilitado' : 'Deshabilitado') : 'Sin acceso' }}</dd></div>
                    @if ($member->emergency_contact_name)
                        <div><dt class="text-xs text-slate-500">Emergencias</dt><dd class="font-medium">{{ $member->emergency_contact_name }} {{ $member->emergency_contact_phone }}</dd></div>
                    @endif
                    @if ($member->medical_notes)
                        <div><dt class="text-xs text-slate-500">Info. médica</dt><dd class="font-medium whitespace-pre-line text-amber-800">{{ $member->medical_notes }}</dd></div>
                    @endif
                    @if ($member->notes)
                        <div><dt class="text-xs text-slate-500">Notas</dt><dd class="whitespace-pre-line text-slate-600">{{ $member->notes }}</dd></div>
                    @endif
                </dl>
            </div>
            @if ($member->holder || $member->dependents->isNotEmpty())
                <div class="card p-6">
                    <h3 class="mb-3 text-sm font-semibold text-slate-900">Grupo familiar</h3>
                    <ul class="space-y-2 text-sm">
                        @if ($member->holder)
                            <li>Titular: <a href="{{ route('admin.members.show', $member->holder) }}" wire:navigate class="font-medium text-brand-700">{{ $member->holder->fullName() }}</a> <span class="text-slate-400">({{ $member->relationship }})</span></li>
                        @endif
                        @foreach ($member->dependents as $dependent)
                            <li><a href="{{ route('admin.members.show', $dependent) }}" wire:navigate class="font-medium text-brand-700">{{ $dependent->fullName() }}</a> <span class="text-slate-400">({{ $dependent->relationship }})</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>

        {{-- Pestañas --}}
        <div class="xl:col-span-3">
            <div class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
                @foreach (['cuenta' => 'Cuenta corriente', ...(uses_gym() ? ['planes' => 'Planes'] : []), 'actividades' => 'Actividades', 'clases' => 'Clases', 'reservas' => 'Reservas', 'accesos' => 'Accesos', 'historial' => 'Historial'] as $key => $label)
                    <button type="button" wire:click="$set('tab', '{{ $key }}')"
                            @class(['-mb-px border-b-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap', 'border-brand-600 text-brand-700' => $tab === $key, 'border-transparent text-slate-500 hover:text-slate-800' => $tab !== $key])>{{ $label }}</button>
                @endforeach
            </div>

            <div wire:loading.class="opacity-50" wire:target="tab">
                @if ($tab === 'cuenta')
                    <div class="card">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                            <h3 class="font-semibold text-slate-900">Cargos</h3>
                            @can('cuotas.gestionar')
                                <button type="button" wire:click="$set('showChargeModal', true)" class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Nuevo cargo</button>
                            @endcan
                        </div>
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead><tr><th>Concepto</th><th>Vence</th><th class="text-right">Importe</th><th class="text-right">Saldo</th><th>Estado</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($fees as $fee)
                                        <tr wire:key="fee-{{ $fee->id }}">
                                            <td>
                                                <p class="font-medium text-slate-800">{{ $fee->concept }}</p>
                                                <p class="text-xs text-slate-500">{{ $fee->type->label() }}@if ((float) $fee->surcharge > 0) · recargo {{ money($fee->surcharge) }}@endif</p>
                                            </td>
                                            <td class="whitespace-nowrap">{{ $fee->due_date->format('d/m/Y') }}</td>
                                            <td class="text-right tabular-nums">{{ money($fee->total()) }}</td>
                                            <td class="text-right font-medium tabular-nums">{{ $fee->isOpen() ? money($fee->balance()) : '—' }}</td>
                                            <td><x-badge :status="$fee->status" /></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5"><x-empty-state icon="document" title="Sin cargos" /></td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card mt-6">
                        <div class="border-b border-slate-100 px-5 py-3"><h3 class="font-semibold text-slate-900">Pagos</h3></div>
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead><tr><th>Recibo</th><th>Fecha</th><th>Medio</th><th class="text-right">Importe</th><th>Estado</th><th></th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($payments as $payment)
                                        <tr wire:key="pay-{{ $payment->id }}">
                                            <td class="font-medium">{{ $payment->receipt_number }}</td>
                                            <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                                            <td>{{ $payment->method->label() }}</td>
                                            <td class="text-right tabular-nums">{{ money($payment->amount) }}</td>
                                            <td><x-badge :status="$payment->status" /></td>
                                            <td class="text-right"><a href="{{ route('admin.payments.receipt', $payment) }}" target="_blank" class="btn-ghost btn-sm"><x-icon name="printer" class="size-4" /></a></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6"><x-empty-state icon="banknotes" title="Sin pagos" /></td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif ($tab === 'actividades')
                    @can('inscripciones.gestionar')
                        @if ($member->isActive())
                            <form wire:submit="enroll" class="card mb-6 flex flex-col gap-3 p-4 sm:flex-row sm:items-start">
                                <x-field error="activityId" class="flex-1">
                                    <select wire:model="activityId" class="form-input">
                                        <option value="">Inscribir en actividad…</option>
                                        @foreach ($activities as $activity)
                                            <option value="{{ $activity->id }}">{{ $activity->name }} — {{ money($activity->monthly_fee) }}/mes</option>
                                        @endforeach
                                    </select>
                                </x-field>
                                <button type="submit" class="btn-primary"><x-icon name="plus" class="size-4" /> Inscribir</button>
                            </form>
                        @endif
                    @endcan
                    <div class="card overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Actividad</th><th>Desde</th><th>Hasta</th><th>Estado</th><th></th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($enrollments as $enrollment)
                                    <tr wire:key="enr-{{ $enrollment->id }}">
                                        <td class="font-medium">{{ $enrollment->activity->name }}</td>
                                        <td>{{ $enrollment->start_date->format('d/m/Y') }}</td>
                                        <td>{{ $enrollment->end_date?->format('d/m/Y') ?? '—' }}</td>
                                        <td><x-badge :status="$enrollment->status" /></td>
                                        <td class="text-right">
                                            @if ($enrollment->status === \App\Enums\EnrollmentStatus::Active)
                                                @can('inscripciones.gestionar')
                                                    <button type="button" wire:click="unenroll({{ $enrollment->id }})" wire:confirm="¿Dar de baja la inscripción?" class="btn-ghost btn-sm text-red-600">Dar de baja</button>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5"><x-empty-state icon="trophy" title="Sin inscripciones" /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($tab === 'planes')
                    @can('suscripciones.gestionar')
                        @if ($member->isActive())
                            <div class="mb-4 flex justify-end">
                                <a href="{{ route('admin.gym.subscriptions', ['socio' => $member->id]) }}" wire:navigate class="btn-primary btn-sm"><x-icon name="plus" class="size-4" /> Asignar plan</a>
                            </div>
                        @endif
                    @endcan
                    <div class="card overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Plan</th><th>Vigencia</th><th class="text-right">Precio</th><th>Visitas</th><th>Estado</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($subscriptions as $s)
                                    <tr>
                                        <td class="font-medium">{{ $s->plan->name }}</td>
                                        <td>{{ $s->periodLabel() }}</td>
                                        <td class="text-right tabular-nums">{{ money($s->price) }}</td>
                                        <td>{{ $s->isCurrent() && $s->plan->visit_limit ? 'Quedan '.$s->visitsRemaining() : $s->plan->visitLimitLabel() }}</td>
                                        <td><x-badge :status="$s->status" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5"><x-empty-state icon="id-card" title="Sin planes contratados" /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($tab === 'clases')
                    @if ($packs->isNotEmpty())
                        <div class="mb-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($packs as $pack)
                                @php($remaining = $pack->visitsRemaining())
                                <div class="card p-4">
                                    <p class="font-medium text-slate-900">{{ $pack->plan->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $pack->plan->instructor?->name }} · vence {{ $pack->end_date->format('d/m/Y') }}</p>
                                    <p class="mt-2 text-sm text-slate-700">
                                        @if ($remaining === null)
                                            Clases ilimitadas
                                        @else
                                            Le quedan <strong @class(['text-red-600' => $remaining === 0])>{{ $remaining }}</strong> de {{ $pack->plan->visit_limit }} {{ \App\Models\Plan::VISIT_PERIODS[$pack->plan->visit_period] ?? '' }}
                                        @endif
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="card overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Fecha</th><th>Horario</th><th>Sede</th><th>Profesor</th><th>Clase</th><th>Asistencia</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($lessons as $lesson)
                                    <tr>
                                        <td>{{ $lesson->date->format('d/m/Y') }}</td>
                                        <td>{{ $lesson->timeRange() }}</td>
                                        <td>{{ $lesson->facility->name }}</td>
                                        <td>{{ $lesson->instructor->name }}</td>
                                        <td><x-badge :status="$lesson->status" /></td>
                                        <td>
                                            <x-badge :status="\App\Enums\AttendanceStatus::from($lesson->pivot->attendance)" />
                                            @if ($lesson->pivot->subscription_id)<span class="text-xs text-slate-500">pack</span>@elseif ($lesson->pivot->fee_id)<span class="text-xs text-slate-500">suelta</span>@endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6"><x-empty-state icon="calendar" title="Sin clases en esta entidad" /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($tab === 'reservas')
                    <div class="card overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Instalación</th><th>Fecha</th><th>Horario</th><th class="text-right">Importe</th><th>Estado</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($reservations as $reservation)
                                    <tr>
                                        <td class="font-medium">{{ $reservation->facility->name }}</td>
                                        <td>{{ $reservation->date->format('d/m/Y') }}</td>
                                        <td>{{ $reservation->timeRange() }}</td>
                                        <td class="text-right tabular-nums">{{ money($reservation->amount) }}</td>
                                        <td><x-badge :status="$reservation->status" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5"><x-empty-state icon="calendar" title="Sin reservas" /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($tab === 'accesos')
                    <div class="card overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Fecha y hora</th><th>Resultado</th><th>Detalle</th><th>Registró</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($accessLogs as $log)
                                    <tr>
                                        <td>{{ $log->checked_at->format('d/m/Y H:i') }}</td>
                                        <td><x-badge :status="$log->result" /></td>
                                        <td>{{ $log->reason ?? '—' }}</td>
                                        <td>{{ $log->checker?->name ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><x-empty-state icon="qr" title="Sin registros de acceso" /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($tab === 'historial')
                    <div class="card divide-y divide-slate-100">
                        @forelse ($audits as $audit)
                            @include('livewire.admin.audit.partials.entry', ['audit' => $audit])
                        @empty
                            <x-empty-state icon="shield" title="Sin cambios registrados" />
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal: suspender / baja / rechazar --}}
    <x-modal wire:model="showStatusModal" :title="match ($statusAction) { 'suspend' => 'Suspender socio', 'deactivate' => 'Dar de baja al socio', 'reject' => 'Rechazar solicitud', default => '' }" max-width="max-w-lg">
        @if ($statusAction === 'deactivate')
            <p class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Se darán de baja sus inscripciones, se cancelarán sus reservas futuras y se deshabilitará su acceso al portal. Las deudas existentes se conservan.</p>
        @endif
        <x-field label="Motivo" for="statusReason" error="statusReason" required>
            <textarea id="statusReason" wire:model="statusReason" rows="3" class="form-input"></textarea>
        </x-field>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="button" wire:click="confirmStatus" class="btn-danger">Confirmar</button>
        </x-slot:footer>
    </x-modal>

    {{-- Modal: cargo manual --}}
    <x-modal wire:model="showChargeModal" title="Nuevo cargo" max-width="max-w-lg">
        <div class="grid gap-4">
            <x-field label="Tipo" for="chargeType" error="chargeType">
                <select id="chargeType" wire:model="chargeType" class="form-input">
                    @foreach ([\App\Enums\FeeType::Other, \App\Enums\FeeType::Admission, \App\Enums\FeeType::Membership, \App\Enums\FeeType::Activity] as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Concepto" for="chargeConcept" error="chargeConcept" required>
                <input id="chargeConcept" wire:model="chargeConcept" class="form-input" placeholder="Ej.: Indumentaria, torneo, cuota atrasada…">
            </x-field>
            <div class="grid grid-cols-2 gap-4">
                <x-field label="Importe" for="chargeAmount" error="chargeAmount" required>
                    <input id="chargeAmount" type="number" step="0.01" min="0" wire:model="chargeAmount" class="form-input">
                </x-field>
                <x-field label="Vencimiento" for="chargeDueDate" error="chargeDueDate" required>
                    <input id="chargeDueDate" type="date" wire:model="chargeDueDate" class="form-input">
                </x-field>
            </div>
        </div>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="button" wire:click="addCharge" class="btn-primary">Agregar cargo</button>
        </x-slot:footer>
    </x-modal>
</div>
