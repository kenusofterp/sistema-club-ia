<section id="{{ $section->key }}" class="scroll-mt-20 py-20 sm:py-28 {{ $alt ? 'bg-slate-50' : 'bg-white' }}">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('site.partials.section-heading')
        <div class="grid gap-8 md:grid-cols-3">
            @forelse ($posts as $post)
                @include('site.partials.post-card', ['post' => $post])
            @empty
                <p class="col-span-full text-center text-slate-500">Aún no hay novedades publicadas.</p>
            @endforelse
        </div>
        @if ($posts->isNotEmpty())
            <div class="mt-12 text-center">
                <a href="{{ route('site.news') }}" class="btn-secondary px-6 py-3">Ver todas las noticias <x-icon name="arrow-right" class="size-4" /></a>
            </div>
        @endif
    </div>
</section>
