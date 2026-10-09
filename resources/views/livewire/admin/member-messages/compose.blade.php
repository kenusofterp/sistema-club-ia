<div>
    <x-page-header title="Mensajes a alumnos" subtitle="Llegan como notificación al celular (a quienes las activaron) y quedan en el portal del alumno." />

    <div class="grid gap-6 xl:grid-cols-3">
        <form wire:submit="send" class="card space-y-5 p-6 xl:col-span-2">
            <x-field label="¿A quiénes?" for="audience" error="audience" required>
                <select id="audience" wire:model.live="audience" class="form-input">
                    <option value="deudores">Los que deben (vencido o vence esta semana)</option>
                    <option value="torneo">Los que deben un torneo</option>
                    <option value="nivel">Todos los alumnos de un {{ mb_strtolower(activity_label()) }}</option>
                    <option value="todos">Todos mis alumnos</option>
                </select>
            </x-field>

            @if ($audience === 'torneo')
                <x-field label="Torneo" for="tournamentId" error="tournamentId" required>
                    <select id="tournamentId" wire:model.live="tournamentId" class="form-input">
                        <option value="">Elegí el torneo…</option>
                        @foreach ($tournaments as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->startsOn()?->format('d/m/Y') }})</option>
                        @endforeach
                    </select>
                </x-field>
            @elseif ($audience === 'nivel')
                <x-field :label="activity_label()" for="activityId" error="activityId" required>
                    <select id="activityId" wire:model.live="activityId" class="form-input">
                        <option value="">Elegí…</option>
                        @foreach ($activities as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            @endif

            <x-field label="Título" for="title" error="title" required>
                <input id="title" wire:model="title" class="form-input" maxlength="120">
            </x-field>
            <x-field label="Mensaje" for="body" error="body" required>
                <textarea id="body" wire:model="body" rows="5" class="form-input" maxlength="1000"></textarea>
            </x-field>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500"><strong class="text-slate-900">{{ $recipients->count() }}</strong> destinatarios</p>
                <button type="submit" class="btn-primary" @disabled($recipients->isEmpty()) wire:confirm="¿Enviar el mensaje a {{ $recipients->count() }} alumnos?"><x-icon name="chat" class="size-4" /> Enviar mensaje</button>
            </div>

            @if ($recipients->isNotEmpty())
                <details class="rounded-lg bg-slate-50 p-3 text-sm">
                    <summary class="cursor-pointer font-medium text-slate-700">Ver destinatarios</summary>
                    <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                        @foreach ($recipients as $member)
                            <li class="flex items-center gap-1.5 text-slate-600">
                                {{ $member->sortableName() }}
                                @unless ($member->user_id) <span class="text-xs text-slate-400" title="No tiene usuario en el portal: no le llega la notificación">· sin portal</span>@endunless
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </form>

        <div class="card">
            <h2 class="border-b border-slate-100 px-5 py-3 font-semibold text-slate-900">Enviados</h2>
            <ul class="divide-y divide-slate-100">
                @forelse ($sent as $message)
                    <li class="px-5 py-3 text-sm" wire:key="m-{{ $message->id }}">
                        <p class="font-medium text-slate-800">{{ $message->title }}</p>
                        <p class="text-xs text-slate-500">{{ $message->created_at->format('d/m/Y H:i') }} · {{ $message->recipients_count }} alumnos{{ $message->context ? ' · '.$message->context : '' }}</p>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-500">Todavía no enviaste mensajes.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
