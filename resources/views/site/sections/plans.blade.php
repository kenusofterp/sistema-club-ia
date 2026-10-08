@if ($plans->isNotEmpty())
<section id="{{ $section->key }}" class="scroll-mt-20 py-20 sm:py-28 {{ $alt ? 'bg-slate-50' : 'bg-white' }}">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('site.partials.section-heading')
        <div @class(['mx-auto grid max-w-6xl gap-6 md:grid-cols-2', 'lg:grid-cols-3' => $plans->count() === 3, 'lg:grid-cols-4' => $plans->count() >= 4])>
            @foreach ($plans as $plan)
                <div @class([
                    'relative flex flex-col rounded-3xl p-7 shadow-sm',
                    'bg-brand-800 text-white ring-2 ring-accent-500 lg:-translate-y-2 shadow-xl' => $plan->is_featured,
                    'bg-white ring-1 ring-slate-200' => ! $plan->is_featured,
                ])>
                    @if ($plan->is_featured)
                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-accent-500 px-3 py-1 text-xs font-bold tracking-wide whitespace-nowrap text-slate-900 uppercase">El más elegido</span>
                    @endif
                    <h3 @class(['font-display text-xl font-bold', 'text-slate-900' => ! $plan->is_featured])>{{ $plan->name }}</h3>
                    @if ($plan->description)
                        <p @class(['mt-1 text-sm', 'text-white/70' => $plan->is_featured, 'text-slate-500' => ! $plan->is_featured])>{{ $plan->description }}</p>
                    @endif
                    <p class="mt-5">
                        <span @class(['font-display text-4xl font-extrabold tracking-tight', 'text-slate-900' => ! $plan->is_featured])>{{ money($plan->price) }}</span>
                        <span @class(['text-sm', 'text-white/70' => $plan->is_featured, 'text-slate-500' => ! $plan->is_featured])>/ {{ $plan->durationLabel() }}</span>
                    </p>
                    <ul @class(['mt-6 space-y-3 text-sm', 'text-white/90' => $plan->is_featured, 'text-slate-600' => ! $plan->is_featured])>
                        <li class="flex gap-2"><x-icon name="check" class="size-5 shrink-0 text-accent-500" /> {{ $plan->access_type->label() }}</li>
                        <li class="flex gap-2"><x-icon name="check" class="size-5 shrink-0 text-accent-500" /> {{ $plan->visitLimitLabel() }}</li>
                        @foreach ($plan->windowsLabels() as $label)
                            <li class="flex gap-2"><x-icon name="check" class="size-5 shrink-0 text-accent-500" /> {{ $label }}</li>
                        @endforeach
                        @if ($plan->activities->isNotEmpty())
                            <li class="flex gap-2"><x-icon name="check" class="size-5 shrink-0 text-accent-500" /> {{ $plan->activities->pluck('name')->implode(', ') }}</li>
                        @endif
                    </ul>
                    <a href="{{ auth()->check() ? route('portal.plans') : (setting('site.join_enabled', true) ? route('site.join') : route('login')) }}"
                       class="{{ $plan->is_featured ? 'btn-accent' : 'btn-primary' }} mt-8 w-full py-3">Quiero este plan</a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
