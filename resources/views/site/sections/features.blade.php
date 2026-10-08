<section id="{{ $section->key }}" class="scroll-mt-20 py-20 sm:py-28 {{ $alt ? 'bg-slate-50' : 'bg-white' }}">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('site.partials.section-heading')
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($section->items ?? [] as $item)
                <div class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="grid size-12 place-items-center rounded-xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white">
                        <x-icon :name="$item['icon'] ?? 'sparkles'" class="size-6" />
                    </div>
                    <h3 class="mt-5 font-display text-lg font-semibold text-slate-900">{{ $item['title'] ?? '' }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $item['text'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
