<x-layouts::site title="Noticias" :transparent-header="true">
    @include('site.partials.page-hero', ['kicker' => setting('site.name'), 'heading' => 'Noticias', 'lead' => 'Novedades, eventos y comunicados del club.'])

    <section class="py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                @forelse ($posts as $post)
                    @include('site.partials.post-card', ['post' => $post])
                @empty
                    <p class="col-span-full text-center text-slate-500">Aún no hay noticias publicadas.</p>
                @endforelse
            </div>
            <div class="mt-12">{{ $posts->links() }}</div>
        </div>
    </section>
</x-layouts::site>
