<article class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-lg">
    <a href="{{ route('site.post', $post) }}" class="block aspect-[16/9] overflow-hidden bg-brand-100">
        @if ($post->imageUrl())
            <img src="{{ $post->imageUrl() }}" alt="{{ $post->title }}" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="grid size-full place-items-center bg-gradient-to-br from-brand-100 to-brand-200 text-brand-400"><x-icon name="newspaper" class="size-12" /></div>
        @endif
    </a>
    <div class="flex flex-1 flex-col p-6">
        <time class="text-xs font-medium tracking-wide text-brand-600 uppercase">{{ $post->published_at?->translatedFormat('j \d\e F, Y') }}</time>
        <h3 class="mt-2 font-display text-lg leading-snug font-semibold text-slate-900 group-hover:text-brand-700">
            <a href="{{ route('site.post', $post) }}">{{ $post->title }}</a>
        </h3>
        @if ($post->excerpt)
            <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $post->excerpt }}</p>
        @endif
        <a href="{{ route('site.post', $post) }}" class="mt-auto inline-flex items-center gap-1 pt-4 text-sm font-semibold text-brand-700">Leer más <x-icon name="arrow-right" class="size-4" /></a>
    </div>
</article>
