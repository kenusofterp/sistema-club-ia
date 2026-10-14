<div>
    <x-page-header title="Control de acceso" :subtitle="$mode === 'ingreso' ? $todayCount.' ingresos permitidos hoy' : 'Tomá lista: todos presentes, destildá a los que faltan'" />

    @if ($canAccess && $canRollCall)
        <div class="mb-4 inline-flex rounded-lg bg-slate-100 p-1 text-sm">
            <button type="button" wire:click="setMode('ingreso')" @class(['flex items-center gap-1.5 rounded-md px-3 py-1.5 font-medium', 'bg-white text-slate-900 shadow-sm' => $mode === 'ingreso', 'text-slate-500' => $mode !== 'ingreso'])><x-icon name="qr" class="size-4" /> Ingreso</button>
            <button type="button" wire:click="setMode('asistencia')" @class(['flex items-center gap-1.5 rounded-md px-3 py-1.5 font-medium', 'bg-white text-slate-900 shadow-sm' => $mode === 'asistencia', 'text-slate-500' => $mode !== 'asistencia'])><x-icon name="clipboard" class="size-4" /> Asistencia por {{ mb_strtolower(activity_label()) }}</button>
        </div>
    @endif

    @if ($mode === 'asistencia')
        <div class="mx-auto max-w-2xl space-y-4">
            <div class="card grid gap-3 p-4 sm:grid-cols-[1fr_11rem]">
                <x-field :label="activity_label()" for="activityId">
                    <select id="activityId" wire:model.live="activityId" class="form-input">
                        <option value="">Elegí…</option>
                        @foreach ($activities as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Fecha" for="date" error="date">
                    <x-date-input id="date" wire:model.live="date" :max="today()->toDateString()" :min="$pastAllowed ? null : today()->toDateString()" :readonly="! $pastAllowed" />
                </x-field>
            </div>

            @if ($activities->isEmpty())
                <div class="card"><x-empty-state icon="clipboard" :title="'No tenés '.mb_strtolower(activity_label(true)).' asignados'" /></div>
            @elseif ($suspended)
                <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700">La clase de ese día está suspendida.</div>
            @elseif ($activityId)
                @php($presentCount = $roster->count() - count($absent))
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3 text-sm">
                        <span class="text-slate-600">Presentes <strong class="text-slate-900">{{ $presentCount }}</strong> de {{ $roster->count() }}
                            @if ($lesson?->status === \App\Enums\LessonStatus::Given) · <span class="text-emerald-700">asistencia ya guardada</span>@endif
                        </span>
                        @if (count($absent))
                            <button type="button" wire:click="allPresent" class="font-medium text-brand-700">Todos presentes</button>
                        @endif
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($roster as $row)
                            @php($isPresent = ! in_array($row['member']->id, $absent, true))
                            <li wire:key="rc-{{ $row['member']->id }}">
                                <label class="flex cursor-pointer items-center gap-3 px-4 py-3">
                                    <input type="checkbox" @checked($isPresent) wire:click="toggleAbsent({{ $row['member']->id }})"
                                           class="size-6 shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <span class="min-w-0 flex-1">
                                        <span @class(['block truncate font-medium', 'text-slate-800' => $isPresent, 'text-slate-400 line-through' => ! $isPresent])>{{ $row['member']->sortableName() }}</span>
                                        @if ($row['notice'])
                                            <span class="block truncate text-xs text-sky-700">{{ $row['notice'] }}</span>
                                        @endif
                                    </span>
                                    <span @class(['text-xs font-semibold', 'text-emerald-700' => $isPresent, 'text-red-600' => ! $isPresent])>{{ $isPresent ? 'Presente' : 'Ausente' }}</span>
                                </label>
                            </li>
                        @empty
                            <li class="p-6 text-center text-sm text-slate-500">Este {{ mb_strtolower(activity_label()) }} no tiene alumnos inscriptos.</li>
                        @endforelse
                    </ul>
                    @if ($roster->isNotEmpty())
                        <div class="border-t border-slate-100 p-4">
                            <button type="button" wire:click="saveAttendance" class="btn-primary w-full py-3 text-base"><x-icon name="check" class="size-5" /> Guardar asistencia</button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @else
    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <div class="card p-6"
                 x-data="{
                    scanning: false, stream: null, supported: 'BarcodeDetector' in window,
                    async scan() {
                        if (!this.supported) return;
                        this.scanning = true;
                        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                        this.$refs.video.srcObject = this.stream;
                        await this.$refs.video.play();
                        const detector = new BarcodeDetector({ formats: ['qr_code'] });
                        const loop = async () => {
                            if (!this.scanning) return;
                            const codes = await detector.detect(this.$refs.video).catch(() => []);
                            if (codes.length) { this.stop(); $wire.set('code', codes[0].rawValue); $wire.check(); return; }
                            requestAnimationFrame(loop);
                        };
                        loop();
                    },
                    stop() { this.scanning = false; this.stream?.getTracks().forEach(t => t.stop()); },
                 }"
                 x-on:access-checked.window="$refs.code.focus()">
                <form wire:submit="check" class="flex flex-col gap-3 sm:flex-row">
                    <div class="relative flex-1">
                        <x-icon name="qr" class="pointer-events-none absolute top-3 left-3 size-5 text-slate-400" />
                        <input x-ref="code" wire:model="code" autofocus autocomplete="off" class="form-input py-3 pl-11 text-base" placeholder="Escaneá el QR o ingresá N° de socio / documento">
                    </div>
                    <button type="submit" class="btn-primary px-6 py-3"><x-icon name="search" class="size-5" /> Verificar</button>
                    <button type="button" x-show="supported" x-on:click="scanning ? stop() : scan()" class="btn-secondary px-4 py-3">
                        <x-icon name="photo" class="size-5" /> <span x-text="scanning ? 'Detener' : 'Cámara'"></span>
                    </button>
                </form>
                @error('code') <p class="form-error">{{ $message }}</p> @enderror
                <video x-ref="video" x-show="scanning" x-cloak playsinline class="mt-4 aspect-video w-full rounded-xl bg-black object-cover"></video>
            </div>

            @if ($notFound)
                <div class="card flex items-center gap-4 border-amber-300 bg-amber-50 p-6">
                    <x-icon name="warning" class="size-10 text-amber-600" />
                    <div>
                        <p class="font-semibold text-amber-900">No se encontró ningún socio</p>
                        <p class="text-sm text-amber-800">Verificá el código ingresado.</p>
                    </div>
                </div>
            @elseif ($last)
                @php($granted = $last->result === \App\Enums\AccessResult::Granted)
                <div @class(['card overflow-hidden border-2', 'border-emerald-500' => $granted, 'border-red-500' => ! $granted]) wire:key="log-{{ $last->id }}">
                    <div @class(['flex items-center gap-3 px-6 py-4 text-white', 'bg-emerald-600' => $granted, 'bg-red-600' => ! $granted])>
                        <x-icon :name="$granted ? 'check-circle' : 'x-circle'" class="size-8" />
                        <p class="font-display text-2xl font-bold">{{ $granted ? 'ACCESO PERMITIDO' : 'ACCESO DENEGADO' }}</p>
                    </div>
                    <div class="flex items-center gap-5 p-6">
                        @if ($last->member->photoUrl())
                            <img src="{{ $last->member->photoUrl() }}" alt="" class="size-24 rounded-xl object-cover">
                        @else
                            <span class="grid size-24 place-items-center rounded-xl bg-slate-100 font-display text-3xl font-bold text-slate-500">{{ $last->member->initials() }}</span>
                        @endif
                        <div>
                            <p class="font-display text-xl font-semibold text-slate-900">{{ $last->member->fullName() }}</p>
                            <p class="text-sm text-slate-500">N° {{ $last->member->member_number }} · {{ $last->member->category->name }} · {{ $last->member->document_type }} {{ $last->member->document_number }}</p>
                            <div class="mt-2"><x-badge :status="$last->member->status" /></div>
                            @if ($last->reason)
                                <p @class(['mt-2 text-sm font-medium', 'text-amber-700' => $granted, 'text-red-700' => ! $granted])>{{ $last->reason }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="card lg:col-span-2">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Últimos registros</h2></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($recent as $log)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $log->member->fullName() }}</p>
                            <p class="text-xs text-slate-500">{{ $log->checked_at->format('d/m H:i') }}@if ($log->reason) · {{ $log->reason }}@endif</p>
                        </div>
                        <x-badge :status="$log->result" />
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">Sin registros.</li>
                @endforelse
            </ul>
        </div>
    </div>
    @endif
</div>
