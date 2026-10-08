<div>
    <x-page-header title="Agenda de clases" subtitle="Clases individuales y grupales de los profesores en todas sus sedes">
        <x-slot:actions>
            @can('cobros.propios')
                <a href="{{ route('admin.lessons.account') }}" wire:navigate class="btn-secondary"><x-icon name="banknotes" class="size-4" /> Mis cobros</a>
            @endcan
            <a href="{{ route('admin.lessons.packs') }}" wire:navigate class="btn-secondary"><x-icon name="id-card" class="size-4" /> Packs</a>
            @if ($canCreate)
                <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Nueva clase</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Barra: vista, alcance, profesor y navegación --}}
    <div class="card mb-4 flex flex-wrap items-center gap-3 p-4">
        <div class="inline-flex rounded-lg bg-slate-100 p-1" role="tablist" aria-label="Vista">
            @foreach (\App\Livewire\Admin\Lessons\Calendar::VIEWS as $key => $label)
                <button type="button" role="tab" wire:click="$set('view', '{{ $key }}')" @class([
                    'rounded-md px-3 py-1.5 text-sm font-medium',
                    'bg-white text-slate-900 shadow-sm' => $view === $key,
                    'text-slate-500 hover:text-slate-700' => $view !== $key,
                ]) aria-selected="{{ $view === $key ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <span>Ver</span>
            <select wire:model.live="scope" class="form-input w-auto py-1.5">
                <option value="todas">Todos mis clubes</option>
                @foreach ($scopeOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>

        @if ($instructorOptions->isNotEmpty())
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <span>Profesor</span>
                <select wire:model.live="instructorFilter" class="form-input w-auto py-1.5">
                    <option value="">Todos</option>
                    @foreach ($instructorOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <div class="flex items-center gap-2 sm:ml-auto">
            <button type="button" wire:click="shift(-1)" class="btn-secondary px-2.5" aria-label="Anterior"><x-icon name="chevron-left" class="size-4" /></button>
            <button type="button" wire:click="$set('date', '{{ today()->toDateString() }}')" class="btn-ghost btn-sm">Hoy</button>
            <button type="button" wire:click="shift(1)" class="btn-secondary px-2.5" aria-label="Siguiente"><x-icon name="chevron-right" class="size-4" /></button>
            <p class="min-w-0 font-medium text-slate-700">
                @if ($view === 'dia')
                    {{ ucfirst($current->translatedFormat('l j \d\e F')) }}
                @elseif ($view === 'semana')
                    {{ $from->translatedFormat('j M') }} – {{ $to->translatedFormat('j M Y') }}
                @else
                    {{ ucfirst($current->translatedFormat('F Y')) }}
                @endif
            </p>
        </div>
    </div>

    {{-- Totales y referencias --}}
    <div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-slate-600">
        <span><strong class="text-slate-900">{{ $stats['count'] }}</strong> clases</span>
        <span><strong class="text-slate-900">{{ str_replace('.', ',', $stats['hours']) }}</strong> h ocupadas</span>
        <span><strong class="text-slate-900">{{ $stats['given'] }}</strong> dadas</span>
        <span><strong class="text-slate-900">{{ $stats['students'] }}</strong> alumnos</span>
        @if ($legend->isNotEmpty())
            <span class="flex flex-wrap items-center gap-2 sm:ml-auto">
                @foreach ($legend as $name => $classes)
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs ring-1 {{ $classes }}">{{ $name }}</span>
                @endforeach
            </span>
        @endif
    </div>

    @if ($view === 'mes')
        {{-- Vista mensual --}}
        <div class="card overflow-hidden">
            <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-xs font-medium uppercase tracking-wide text-slate-500">
                @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dayName)
                    <div class="py-2">{{ $dayName }}</div>
                @endforeach
            </div>
            <div class="grid grid-cols-7">
                @for ($day = $from->copy(); $day->lte($to); $day->addDay())
                    @php($dayLessons = $lessonsByDay->get($day->toDateString(), collect()))
                    <div wire:key="m-{{ $day->toDateString() }}" @class([
                        'min-h-24 border-b border-r border-slate-100 p-1.5 text-left sm:min-h-28',
                        'bg-slate-50/60' => ! $day->isSameMonth($current),
                    ])>
                        <button type="button" wire:click="goTo('{{ $day->toDateString() }}')" @class([
                            'mb-1 inline-flex size-6 items-center justify-center rounded-full text-xs font-medium',
                            'bg-brand-600 text-white' => $day->isToday(),
                            'text-slate-400' => ! $day->isToday() && ! $day->isSameMonth($current),
                            'text-slate-700 hover:bg-slate-100' => ! $day->isToday() && $day->isSameMonth($current),
                        ])>{{ $day->day }}</button>
                        <div class="space-y-1">
                            @foreach ($dayLessons->take(3) as $lesson)
                                <button type="button" wire:click="show({{ $lesson->id }})" @class([
                                    'block w-full truncate rounded px-1.5 py-0.5 text-left text-[11px] ring-1',
                                    $colorFor($lesson),
                                    'line-through opacity-50' => $lesson->status === \App\Enums\LessonStatus::Cancelled,
                                ])>
                                    <span class="tabular-nums">{{ substr($lesson->start_time, 0, 5) }}</span>
                                    <span class="hidden sm:inline">{{ $lesson->students->count() === 1 ? $lesson->students->first()->last_name : $lesson->students->count().' al.' }}</span>
                                </button>
                            @endforeach
                            @if ($dayLessons->count() > 3)
                                <button type="button" wire:click="goTo('{{ $day->toDateString() }}')" class="text-[11px] font-medium text-brand-700 hover:underline">+{{ $dayLessons->count() - 3 }} más</button>
                            @endif
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    @else
        {{-- Vista de día o semana: grilla horaria --}}
        @php($rowHeight = 56)
        @php($firstHour = $hours[0])
        <div class="card overflow-x-auto">
            <div @class(['min-w-[760px]' => $view === 'semana'])>
                <div class="grid border-b border-slate-200 bg-slate-50" style="grid-template-columns: 3.5rem repeat({{ count($visibleDays) }}, minmax(0, 1fr))">
                    <div></div>
                    @foreach ($visibleDays as $day)
                        <button type="button" wire:click="goTo('{{ $day->toDateString() }}')" @class([
                            'py-2 text-center text-xs font-medium uppercase tracking-wide',
                            'text-brand-700' => $day->isToday(),
                            'text-slate-500 hover:text-slate-700' => ! $day->isToday(),
                        ])>
                            {{ $day->translatedFormat('D') }} <span class="text-sm normal-case">{{ $day->day }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="relative grid" style="grid-template-columns: 3.5rem repeat({{ count($visibleDays) }}, minmax(0, 1fr))">
                    {{-- Columna de horas --}}
                    <div>
                        @foreach ($hours as $hour)
                            <div class="relative border-b border-slate-100 pr-2 text-right text-xs tabular-nums text-slate-400" style="height: {{ $rowHeight }}px">
                                <span class="relative -top-2">{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00</span>
                            </div>
                        @endforeach
                    </div>
                    {{-- Días --}}
                    @foreach ($visibleDays as $day)
                        @php($dayLessons = $lessonsByDay->get($day->toDateString(), collect())->sortBy('start_time')->values())
                        @php($lanes = [])
                        @php($laneOf = [])
                        @foreach ($dayLessons as $lesson)
                            @php($lane = collect($lanes)->search(fn ($end) => $end <= $lesson->start_time))
                            @php($lane = $lane === false ? count($lanes) : $lane)
                            @php($lanes[$lane] = $lesson->end_time)
                            @php($laneOf[$lesson->id] = $lane)
                        @endforeach
                        @php($laneCount = max(1, count($lanes)))
                        <div class="relative border-l border-slate-100" wire:key="d-{{ $day->toDateString() }}">
                            @foreach ($hours as $hour)
                                @if ($canCreate)
                                    <button type="button" wire:click="create('{{ $day->toDateString() }}', '{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00')"
                                        class="block w-full border-b border-slate-100 hover:bg-brand-50/50" style="height: {{ $rowHeight }}px"
                                        aria-label="Nueva clase el {{ $day->format('d/m') }} a las {{ $hour }}:00"></button>
                                @else
                                    <div class="border-b border-slate-100" style="height: {{ $rowHeight }}px"></div>
                                @endif
                            @endforeach
                            @foreach ($dayLessons as $lesson)
                                @php([$h, $m] = array_map('intval', explode(':', $lesson->start_time)))
                                @php($top = (($h - $firstHour) * 60 + $m) * $rowHeight / 60)
                                @php($height = max(22, $lesson->minutes() * $rowHeight / 60 - 2))
                                <button type="button" wire:key="l-{{ $lesson->id }}" wire:click="show({{ $lesson->id }})"
                                    @class([
                                        'absolute overflow-hidden rounded-md px-1.5 py-1 text-left text-xs shadow-sm ring-1 hover:z-10 hover:shadow-md',
                                        $colorFor($lesson),
                                        'opacity-50 line-through' => $lesson->status === \App\Enums\LessonStatus::Cancelled,
                                    ])
                                    style="top: {{ $top }}px; height: {{ $height }}px; left: calc({{ $laneOf[$lesson->id] * 100 / $laneCount }}% + 2px); width: calc({{ 100 / $laneCount }}% - 4px)">
                                    <span class="flex items-center gap-1 font-semibold">
                                        @if ($lesson->status === \App\Enums\LessonStatus::Given)<x-icon name="check" class="size-3 shrink-0" />@endif
                                        <span class="tabular-nums">{{ substr($lesson->start_time, 0, 5) }}</span>
                                        <span class="truncate">{{ $lesson->students->count() === 1 ? $lesson->students->first()->fullName() : ($lesson->students->isEmpty() ? 'Sin alumnos' : $lesson->students->count().' alumnos') }}</span>
                                    </span>
                                    <span class="block truncate opacity-80">{{ $scope === 'todas' ? $lesson->organization->name.' · ' : '' }}{{ $lesson->facility->name }}</span>
                                    @if ($instructorOptions->isNotEmpty() && $instructorFilter === '')
                                        <span class="block truncate opacity-80">{{ $lesson->instructor->name }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Formulario de clase --}}
    <x-modal wire:model="showForm" :title="$editingId ? 'Editar clase' : 'Nueva clase'" max-width="max-w-2xl">
        <form wire:submit="save" id="lesson-form" class="grid gap-4 sm:grid-cols-2">
            @if ($instructorOptions->isNotEmpty() && ! $editingId)
                <x-field label="Profesor" for="formInstructorId" error="formInstructorId" required class="sm:col-span-2">
                    <select id="formInstructorId" wire:model.live="formInstructorId" class="form-input">
                        <option value="{{ auth()->id() }}">{{ auth()->user()->name }} (yo)</option>
                        @foreach ($instructorOptions as $id => $name)
                            @continue($id === auth()->id())
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </x-field>
            @endif

            <x-field label="Club y sede" for="facilityId" error="facilityId" required class="sm:col-span-2">
                <select id="facilityId" wire:model.live="facilityId" class="form-input" @disabled($editingId)>
                    <option value="">Seleccionar…</option>
                    @foreach ($formFacilities->groupBy(fn ($f) => $f->organization->name) as $club => $facilities)
                        <optgroup label="{{ $club }}">
                            @foreach ($facilities as $facility)
                                <option value="{{ $facility->id }}">{{ $facility->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @if ($formFacilities->isEmpty())
                    <p class="mt-1 text-xs text-amber-700">El profesor no tiene sedes asignadas. Se asignan desde Administración › Usuarios.</p>
                @endif
            </x-field>

            <x-field label="Fecha" for="formDate" error="formDate" required>
                <input id="formDate" type="date" wire:model.live="formDate" class="form-input">
            </x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Inicio" for="startTime" error="startTime" required>
                    <input id="startTime" type="time" step="300" wire:model.live.blur="startTime" class="form-input">
                </x-field>
                <x-field label="Fin" for="endTime" error="endTime" required>
                    <input id="endTime" type="time" step="300" wire:model.live.blur="endTime" class="form-input">
                </x-field>
            </div>

            @if ($formConflicts->isNotEmpty())
                <div class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800 sm:col-span-2">
                    <p class="font-medium">La sede ya está ocupada en ese horario:</p>
                    <ul class="mt-1 list-disc pl-4">
                        @foreach ($formConflicts as $conflict)<li>{{ $conflict }}</li>@endforeach
                    </ul>
                    <p class="mt-1">Podés programarla igual si hay más de una cancha.</p>
                </div>
            @endif

            <x-field label="Precio de la clase suelta" for="price" error="price">
                <input id="price" type="number" step="0.01" min="0" wire:model="price" class="form-input" placeholder="{{ $defaultPrice !== null ? 'Por defecto: '.money($defaultPrice) : 'Por defecto' }}">
                <p class="mt-1 text-xs text-slate-500">Solo lo pagan los alumnos sin pack con clases disponibles.</p>
            </x-field>
            <x-field label="Notas" for="notes" error="notes">
                <input id="notes" wire:model="notes" class="form-input" placeholder="Ej.: traer pelotas nuevas">
            </x-field>

            @unless ($editingId)
                <div class="sm:col-span-2">
                    <x-field label="Alumnos" for="studentSearch" error="students">
                        <div class="relative">
                            <input id="studentSearch" wire:model.live.debounce.300ms="studentSearch" class="form-input" placeholder="Buscar por nombre o documento en todo el sistema…" autocomplete="off">
                            @if ($studentResults->isNotEmpty())
                                <ul class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-slate-200">
                                    @foreach ($studentResults as $result)
                                        <li><button type="button" wire:click="addStudent({{ $result['id'] }})" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50">{{ $result['name'] }} <span class="text-slate-400">· {{ $result['detail'] }}</span></button></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </x-field>
                    @if ($students)
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($students as $student)
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 py-1 pl-3 pr-1 text-sm text-slate-700">
                                    {{ $student['name'] }}
                                    <button type="button" wire:click="removeStudentFromForm({{ $student['id'] }})" class="rounded-full p-0.5 text-slate-400 hover:bg-slate-200 hover:text-slate-600" aria-label="Quitar"><x-icon name="x" class="size-3.5" /></button>
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-1 text-xs text-slate-500">Podés dejarla sin alumnos y sumarlos después. Varios alumnos = clase grupal.</p>
                    @endif
                </div>

                <div class="rounded-lg border border-slate-200 p-3 sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" wire:model.live="repeat" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Repetir semanalmente
                    </label>
                    @if ($repeat)
                        <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_auto]">
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'] as $value => $label)
                                    <label @class([
                                        'cursor-pointer rounded-lg px-2.5 py-1.5 text-sm ring-1',
                                        'bg-brand-600 text-white ring-brand-600' => in_array((string) $value, $days, true),
                                        'text-slate-600 ring-slate-200 hover:bg-slate-50' => ! in_array((string) $value, $days, true),
                                    ])>
                                        <input type="checkbox" value="{{ $value }}" wire:model.live="days" class="sr-only">{{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <x-field label="Hasta" for="until" error="until">
                                <input id="until" type="date" wire:model="until" class="form-input">
                            </x-field>
                        </div>
                        @error('days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @endif
                </div>
            @endunless
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="lesson-form" class="btn-primary">{{ $editingId ? 'Guardar cambios' : 'Programar' }}</button>
        </x-slot:footer>
    </x-modal>

    {{-- Detalle de la clase --}}
    <x-modal wire:model="showDetail" title="Clase" max-width="max-w-2xl">
        @if ($detail)
            <div class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900">{{ ucfirst($detail->date->translatedFormat('l j \d\e F')) }} · {{ $detail->timeRange() }}</p>
                        <p class="text-sm text-slate-500">{{ $detail->organization->name }} · {{ $detail->facility->name }} · {{ $detail->instructor->name }}</p>
                        @if ($detail->series_id)
                            <p class="mt-1 text-xs text-slate-500"><x-icon name="refresh" class="inline size-3.5" /> Se repite hasta el {{ $detail->series->ends_on->format('d/m/Y') }}</p>
                        @endif
                        @if ($detail->notes)<p class="mt-1 text-sm text-slate-600">{{ $detail->notes }}</p>@endif
                    </div>
                    <x-badge :status="$detail->status" />
                </div>

                <div>
                    <h4 class="mb-2 text-sm font-semibold text-slate-900">Alumnos ({{ $detail->students->count() }})</h4>
                    <ul class="divide-y divide-slate-100 rounded-lg ring-1 ring-slate-200">
                        @forelse ($detail->students as $student)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm" wire:key="st-{{ $student->id }}">
                                <span class="font-medium text-slate-800">{{ $student->fullName() }}</span>
                                @if ($detail->isScheduled() && $detailCanManage)
                                    <span class="flex items-center gap-2">
                                        <span class="inline-flex rounded-lg bg-slate-100 p-0.5">
                                            @foreach (['presente' => 'Presente', 'ausente' => 'Ausente'] as $value => $label)
                                                <label @class([
                                                    'cursor-pointer rounded-md px-2 py-1 text-xs font-medium',
                                                    'bg-white shadow-sm '.($value === 'presente' ? 'text-emerald-700' : 'text-red-700') => ($attendance[$student->id] ?? 'presente') === $value,
                                                    'text-slate-500' => ($attendance[$student->id] ?? 'presente') !== $value,
                                                ])>
                                                    <input type="radio" value="{{ $value }}" wire:model.live="attendance.{{ $student->id }}" class="sr-only">{{ $label }}
                                                </label>
                                            @endforeach
                                        </span>
                                        <button type="button" wire:click="removeStudentFromLesson({{ $student->id }})" wire:confirm="¿Quitar a {{ $student->fullName() }} de esta clase?" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-red-600" aria-label="Quitar"><x-icon name="trash" class="size-4" /></button>
                                    </span>
                                @else
                                    <span class="flex items-center gap-2 text-xs">
                                        <x-badge :status="\App\Enums\AttendanceStatus::from($student->pivot->attendance)" />
                                        @if ($student->pivot->subscription_id)
                                            <span class="text-slate-500">Descontada del pack</span>
                                        @elseif ($fee = $detailFees->get($student->id))
                                            <span class="text-slate-500">Clase suelta {{ money($fee->amount) }} · {{ $fee->status->label() }}</span>
                                        @endif
                                    </span>
                                @endif
                            </li>
                        @empty
                            <li class="px-3 py-3 text-sm text-slate-500">Sin alumnos todavía.</li>
                        @endforelse
                    </ul>

                    @if ($detail->isScheduled() && $detailCanManage)
                        <div class="relative mt-2">
                            <input wire:model.live.debounce.300ms="detailSearch" class="form-input" placeholder="Agregar alumno: nombre o documento…" autocomplete="off" aria-label="Agregar alumno">
                            @if ($detailResults->isNotEmpty())
                                <ul class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-slate-200">
                                    @foreach ($detailResults as $result)
                                        <li><button type="button" wire:click="addStudentToLesson({{ $result['id'] }})" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50">{{ $result['name'] }} <span class="text-slate-400">· {{ $result['detail'] }}</span></button></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($detail->isScheduled() && $detailCanManage)
                    <p class="rounded-lg bg-sky-50 p-3 text-xs text-sky-800">Al marcarla como dada, a cada presente se le descuenta una clase de su pack con {{ $detail->instructor->name }}; si no tiene pack o no le quedan clases, se le cobra la clase suelta. Los ausentes no consumen.</p>
                @endif
            </div>
        @endif
        <x-slot:footer>
            @if ($detail && $detailCanManage)
                @if ($detail->isScheduled())
                    <div class="mr-auto flex flex-wrap gap-1">
                        <button type="button" wire:click="cancelLesson" wire:confirm="¿Cancelar esta clase?" class="btn-ghost btn-sm text-red-600">Cancelar clase</button>
                        @if ($detail->series_id)
                            <button type="button" wire:click="cancelSeries" wire:confirm="¿Cancelar esta clase y todas las siguientes de la serie?" class="btn-ghost btn-sm text-red-600">Cancelar desde acá</button>
                        @endif
                    </div>
                    <button type="button" wire:click="edit({{ $detail->id }})" class="btn-secondary">Editar</button>
                    @if (! $detail->date->isFuture())
                        <button type="button" wire:click="markGiven" class="btn-primary"><x-icon name="check" class="size-4" /> Marcar como dada</button>
                    @endif
                @elseif ($detail->status === \App\Enums\LessonStatus::Given)
                    <button type="button" wire:click="reopen" wire:confirm="Se anularán los cargos de clase suelta sin pagar. ¿Reabrir la clase?" class="btn-secondary">Reabrir</button>
                @endif
            @endif
        </x-slot:footer>
    </x-modal>
</div>
