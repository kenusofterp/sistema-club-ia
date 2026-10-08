<x-layouts::site title="Actividades" :transparent-header="true">
    @include('site.partials.page-hero', ['kicker' => setting('site.name'), 'heading' => 'Actividades', 'lead' => 'Disciplinas deportivas, recreativas y culturales para todas las edades.'])

    <section class="py-16 sm:py-20">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
            @forelse ($activities as $activity)
                @include('site.partials.activity-card', ['activity' => $activity])
            @empty
                <p class="col-span-full text-center text-slate-500">Próximamente publicaremos nuestras actividades.</p>
            @endforelse
        </div>
    </section>
</x-layouts::site>
