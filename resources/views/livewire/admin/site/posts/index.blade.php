<div>
    <x-page-header title="Noticias" subtitle="Novedades publicadas en el sitio">
        <x-slot:actions>
            <a href="{{ route('admin.site.posts.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" class="size-4" /> Nueva noticia</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="border-b border-slate-200 p-4"><input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar noticia…" class="form-input max-w-md"></div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Noticia</th><th>Publicación</th><th>Estado</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($posts as $post)
                        <tr wire:key="post-{{ $post->id }}">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-16 shrink-0 overflow-hidden rounded bg-slate-100">
                                        @if ($post->imageUrl())<img src="{{ $post->imageUrl() }}" alt="" class="size-full object-cover">@endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-slate-900">{{ $post->title }}</p>
                                        <p class="truncate text-xs text-slate-500">/noticias/{{ $post->slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">{{ $post->published_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td><x-badge :status="$post->status" /></td>
                            <td class="text-right whitespace-nowrap">
                                @if ($post->status === \App\Enums\PostStatus::Published)
                                    <a href="{{ route('site.post', $post) }}" target="_blank" class="btn-ghost btn-sm"><x-icon name="external" class="size-4" /></a>
                                @endif
                                <a href="{{ route('admin.site.posts.edit', $post->id) }}" wire:navigate class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /></a>
                                <button type="button" wire:click="delete({{ $post->id }})" wire:confirm="¿Eliminar la noticia?" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state icon="newspaper" title="Sin noticias" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($posts->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $posts->links() }}</div>@endif
    </div>
</div>
