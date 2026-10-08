<section id="{{ $section->key }}" class="scroll-mt-20 py-20 sm:py-28 {{ $alt ? 'bg-slate-50' : 'bg-white' }}">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('site.partials.section-heading')
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($activities as $activity)
                @include('site.partials.activity-card', ['activity' => $activity])
            @empty
                <p class="col-span-full text-center text-slate-500">Próximamente publicaremos nuestras actividades.</p>
            @endforelse
        </div>
        <div class="mt-12 text-center">
            <a href="{{ route('site.activities') }}" class="btn-secondary px-6 py-3">Ver todas las actividades <x-icon name="arrow-right" class="size-4" /></a>
        </div>
    </div>
</section>
