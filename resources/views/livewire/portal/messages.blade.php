<div class="space-y-6">
    <div>
        <h1 class="font-display text-2xl font-bold text-slate-900">Mensajes</h1>
        <p class="text-sm text-slate-500">Lo que te mandaron tu profesor o el club.</p>
    </div>

    <div class="card divide-y divide-slate-100">
        @forelse ($messages as $message)
            <article class="p-5" wire:key="m-{{ $message->id }}">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="font-semibold text-slate-900">{{ $message->title }}</h2>
                    @if (in_array($message->id, $unread))
                        <x-badge color="blue">Nuevo</x-badge>
                    @endif
                </div>
                <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $message->body }}</p>
                <p class="mt-2 text-xs text-slate-400">{{ $message->created_at->format('d/m/Y H:i') }}{{ $message->sender ? ' · '.$message->sender->name : '' }}</p>
            </article>
        @empty
            <p class="p-6 text-center text-sm text-slate-500">No tenés mensajes.</p>
        @endforelse
    </div>
</div>
