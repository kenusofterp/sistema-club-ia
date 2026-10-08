<x-layouts::site :title="$activity->name" :description="$activity->summary" :transparent-header="true">
    @include('site.partials.page-hero', ['kicker' => 'Actividades', 'heading' => $activity->name, 'lead' => $activity->summary, 'image' => $activity->imageUrl()])

    <section class="py-16 sm:py-20">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
            <div class="lg:col-span-2">
                @if ($activity->imageUrl())
                    <img src="{{ $activity->imageUrl() }}" alt="{{ $activity->name }}" class="mb-8 aspect-video w-full rounded-2xl object-cover shadow">
                @endif
                <div class="prose-club text-lg text-slate-600">
                    @foreach (preg_split("/\n\s*\n/", (string) ($activity->description ?: $activity->summary)) as $paragraph)
                        <p>{!! nl2br(e($paragraph)) !!}</p>
                    @endforeach
                </div>
            </div>
            <aside class="space-y-6">
                <div class="card p-6">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Información</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        @if ((float) $activity->monthly_fee > 0)
                            <div class="flex justify-between"><dt class="text-slate-500">Cuota mensual</dt><dd class="font-semibold text-slate-900">{{ money($activity->monthly_fee) }}</dd></div>
                        @endif
                        @if ($activity->min_age || $activity->max_age)
                            <div class="flex justify-between"><dt class="text-slate-500">Edades</dt><dd class="font-medium">{{ $activity->min_age ?? 0 }}{{ $activity->max_age ? ' a '.$activity->max_age : '+' }} años</dd></div>
                        @endif
                        @if (($spots = $activity->availableSpots()) !== null)
                            <div class="flex justify-between"><dt class="text-slate-500">Cupos disponibles</dt><dd class="font-medium {{ $spots ? 'text-brand-700' : 'text-red-600' }}">{{ $spots ?: 'Sin cupo' }}</dd></div>
                        @endif
                        @if ($activity->instructor)
                            <div class="flex justify-between"><dt class="text-slate-500">Profesor/a</dt><dd class="font-medium">{{ $activity->instructor->name }}</dd></div>
                        @endif
                    </dl>
                </div>
                @if ($activity->schedules->isNotEmpty())
                    <div class="card p-6">
                        <h2 class="font-display text-lg font-semibold text-slate-900">Horarios</h2>
                        <ul class="mt-4 divide-y divide-slate-100 text-sm">
                            @foreach ($activity->schedules as $schedule)
                                <li class="flex items-center justify-between py-2">
                                    <span class="font-medium text-slate-700">{{ $schedule->dayName() }}</span>
                                    <span class="text-slate-500">{{ $schedule->timeRange() }}@if ($schedule->location) · {{ $schedule->location }}@endif</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="rounded-2xl bg-brand-50 p-6">
                    <p class="text-sm text-slate-700">Los socios se inscriben desde el portal. ¿Todavía no sos socio?</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('portal.activities') }}" class="btn-primary">Inscribirme</a>
                        @if (setting('site.join_enabled', true))
                            <a href="{{ route('site.join') }}" class="btn-secondary">Asociarme</a>
                        @endif
                    </div>
                </div>
            </aside>
        </div>
    </section>
</x-layouts::site>
