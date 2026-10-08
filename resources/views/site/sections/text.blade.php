<section id="{{ $section->key }}" class="scroll-mt-20 py-20 sm:py-24 {{ $alt ? 'bg-slate-50' : 'bg-white' }}">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        @include('site.partials.section-heading')
        @if ($section->imageUrl())
            <img src="{{ $section->imageUrl() }}" alt="" loading="lazy" class="mb-8 w-full rounded-2xl object-cover shadow">
        @endif
        <div class="prose-club text-lg text-slate-600">
            @foreach (preg_split("/\n\s*\n/", (string) $section->content) as $paragraph)
                <p>{!! nl2br(e($paragraph)) !!}</p>
            @endforeach
        </div>
    </div>
</section>
