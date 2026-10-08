<x-layouts::site :title="$post->title" :description="$post->excerpt" :transparent-header="true">
    @include('site.partials.page-hero', ['kicker' => $post->published_at?->translatedFormat('j \d\e F, Y'), 'heading' => $post->title, 'image' => $post->imageUrl()])

    <article class="py-16 sm:py-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            @if ($post->imageUrl())
                <img src="{{ $post->imageUrl() }}" alt="{{ $post->title }}" class="mb-10 aspect-video w-full rounded-2xl object-cover shadow">
            @endif
            @if ($post->excerpt)
                <p class="mb-8 text-xl leading-relaxed font-medium text-slate-700">{{ $post->excerpt }}</p>
            @endif
            <div class="prose-club text-lg text-slate-600">
                @foreach (preg_split("/\n\s*\n/", $post->body) as $paragraph)
                    <p>{!! nl2br(e($paragraph)) !!}</p>
                @endforeach
            </div>
            <a href="{{ route('site.news') }}" class="mt-10 inline-flex items-center gap-2 text-sm font-semibold text-brand-700"><x-icon name="arrow-left" class="size-4" /> Volver a noticias</a>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="bg-slate-50 py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="mb-8 font-display text-2xl font-bold text-slate-900">Otras noticias</h2>
                <div class="grid gap-8 md:grid-cols-3">
                    @foreach ($related as $item)
                        @include('site.partials.post-card', ['post' => $item])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts::site>
