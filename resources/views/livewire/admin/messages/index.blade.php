<div>
    <x-page-header title="Mensajes" subtitle="Consultas recibidas desde el formulario de contacto de la web">
        <x-slot:actions>
            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" wire:model.live="onlyUnread" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Solo no leídos</label>
        </x-slot:actions>
    </x-page-header>

    <div class="card divide-y divide-slate-100">
        @forelse ($messages as $message)
            <div wire:key="msg-{{ $message->id }}">
                <button type="button" wire:click="open({{ $message->id }})" class="flex w-full items-start gap-3 px-5 py-4 text-left hover:bg-slate-50">
                    <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $message->read_at ? 'bg-transparent' : 'bg-brand-600' }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex justify-between gap-3">
                            <p class="truncate text-sm {{ $message->read_at ? 'text-slate-700' : 'font-semibold text-slate-900' }}">{{ $message->name }} <span class="font-normal text-slate-400">&lt;{{ $message->email }}&gt;</span></p>
                            <span class="shrink-0 text-xs text-slate-400">{{ $message->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <p class="truncate text-sm text-slate-500">{{ $message->subject ? $message->subject.' — ' : '' }}{{ $message->message }}</p>
                    </div>
                </button>
                @if ($openId === $message->id)
                    <div class="bg-slate-50 px-10 pb-5">
                        <p class="text-sm whitespace-pre-line text-slate-700">{{ $message->message }}</p>
                        @if ($message->phone)<p class="mt-3 text-sm text-slate-500">Teléfono: {{ $message->phone }}</p>@endif
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.($message->subject ?: 'Consulta')) }}" class="btn-primary btn-sm"><x-icon name="mail" class="size-4" /> Responder</a>
                            <button type="button" wire:click="markUnread({{ $message->id }})" class="btn-secondary btn-sm">Marcar como no leído</button>
                            <button type="button" wire:click="delete({{ $message->id }})" wire:confirm="¿Eliminar el mensaje?" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /> Eliminar</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <x-empty-state icon="inbox" title="No hay mensajes" />
        @endforelse
    </div>
    <div class="mt-4">{{ $messages->links() }}</div>
</div>
