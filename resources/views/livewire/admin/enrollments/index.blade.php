<div>
    <x-page-header title="Inscripciones" :subtitle="'Socios inscriptos en cada '.mb_strtolower(activity_label())">
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Nueva inscripción</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 md:flex-row">
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Buscar socio…" class="form-input md:flex-1">
            <select wire:model.live="activity" class="form-input md:w-64">
                <option value="">{{ activity_label(true) }}: todas</option>
                @foreach ($activities as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="status" class="form-input md:w-44">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Socio</th><th>{{ activity_label() }}</th><th>Desde</th><th>Hasta</th><th class="text-right">Cuota</th><th>Estado</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($enrollments as $enrollment)
                        <tr wire:key="e-{{ $enrollment->id }}">
                            <td><a href="{{ route('admin.members.show', $enrollment->member) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $enrollment->member->sortableName() }}</a></td>
                            <td>{{ $enrollment->activity->name }}</td>
                            <td>{{ $enrollment->start_date->format('d/m/Y') }}</td>
                            <td>{{ $enrollment->end_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="text-right tabular-nums">
                                @if ($editingFeeId === $enrollment->id)
                                    <form wire:submit="saveFee" class="flex flex-wrap items-center justify-end gap-1">
                                        <input type="number" step="0.01" min="0" wire:model="feeAmount" class="form-input w-28 py-1 text-right" placeholder="{{ $enrollment->activity->monthly_fee }}" aria-label="Cuota individual fija" title="Cuota individual fija (vacío = cuota general)">
                                        <span class="flex items-center gap-1">
                                            <input type="number" step="0.01" min="0" max="100" wire:model="scholarship" class="form-input w-20 py-1 text-right" placeholder="0" aria-label="Beca en porcentaje" title="Beca en % sobre la cuota mensual">
                                            <span class="text-xs text-slate-500">%</span>
                                        </span>
                                        <button type="submit" class="btn-primary btn-sm">OK</button>
                                        <button type="button" wire:click="$set('editingFeeId', null)" class="btn-ghost btn-sm">Cancelar</button>
                                    </form>
                                    @error('feeAmount') <p class="form-error">{{ $message }}</p> @enderror
                                    @error('scholarship') <p class="form-error">{{ $message }}</p> @enderror
                                @else
                                    <button type="button" wire:click="editFee({{ $enrollment->id }})" class="hover:text-brand-700" title="Cuota individual fija y beca en %">
                                        {{ money($enrollment->fee_amount ?? $enrollment->activity->monthly_fee) }}
                                        @if ($enrollment->fee_amount !== null) <span class="text-xs text-amber-600">fija</span>@endif
                                        @if ($enrollment->scholarship_percent !== null) <span class="text-xs text-emerald-700">beca {{ rtrim(rtrim(number_format((float) $enrollment->scholarship_percent, 2, ',', ''), '0'), ',') }} %</span>@endif
                                    </button>
                                @endif
                            </td>
                            <td><x-badge :status="$enrollment->status" /></td>
                            <td class="text-right">
                                @if ($enrollment->status === \App\Enums\EnrollmentStatus::Active)
                                    <button type="button" wire:click="unenroll({{ $enrollment->id }})" wire:confirm="¿Dar de baja la inscripción?" class="btn-ghost btn-sm text-red-600">Dar de baja</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="clipboard" title="Sin inscripciones" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($enrollments->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $enrollments->links() }}</div>
        @endif
    </div>

    <x-modal wire:model="showForm" title="Nueva inscripción" max-width="max-w-lg">
        <div class="space-y-4">
            <x-field label="Socio" for="memberSearch" error="memberId" required>
                <div class="relative">
                    <input id="memberSearch" wire:model.live.debounce.300ms="memberSearch" class="form-input" placeholder="Nombre, documento o N° de socio" autocomplete="off">
                    @if ($memberResults->isNotEmpty())
                        <ul class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-slate-200">
                            @foreach ($memberResults as $result)
                                <li><button type="button" wire:click="selectMember({{ $result->id }})" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50">{{ $result->sortableName() }} <span class="text-slate-400">· N° {{ $result->member_number }}</span></button></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-field>
            <x-field label="Actividad" for="activityId" error="activityId" required>
                <select id="activityId" wire:model="activityId" class="form-input">
                    <option value="">Seleccionar…</option>
                    @foreach ($activities->where('is_active', true) as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Beca (%)" for="newScholarship" error="newScholarship">
                    <input id="newScholarship" type="number" step="0.01" min="0" max="100" wire:model="newScholarship" class="form-input" placeholder="Sin beca">
                </x-field>
                <x-field label="Cuota individual fija" for="newFeeAmount" error="newFeeAmount">
                    <input id="newFeeAmount" type="number" step="0.01" min="0" wire:model="newFeeAmount" class="form-input" placeholder="Cuota general">
                </x-field>
            </div>
            <p class="text-xs text-slate-500">La beca en % se aplica solo a la cuota mensual (no a torneos ni a la inscripción anual) y se puede cambiar más adelante. Se validan edad, cupo y deuda del socio según la configuración del club.</p>
        </div>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="button" wire:click="enroll" class="btn-primary">Inscribir</button>
        </x-slot:footer>
    </x-modal>
</div>
