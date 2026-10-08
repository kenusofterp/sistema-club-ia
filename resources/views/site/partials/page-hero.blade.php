<section class="relative isolate overflow-hidden bg-brand-900 pt-32 pb-16 sm:pb-20">
    @if (! empty($image))
        <img src="{{ $image }}" alt="" class="absolute inset-0 -z-10 size-full object-cover opacity-25">
    @endif
    <div class="absolute -top-32 -right-32 -z-10 size-96 rounded-full bg-accent-500/20 blur-3xl"></div>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @isset($kicker)
            <p class="text-sm font-semibold tracking-wider text-accent-400 uppercase">{{ $kicker }}</p>
        @endisset
        <h1 class="mt-2 max-w-4xl font-display text-4xl font-extrabold tracking-tight text-white sm:text-5xl">{{ $heading }}</h1>
        @isset($lead)
            <p class="mt-4 max-w-2xl text-lg text-white/75">{{ $lead }}</p>
        @endisset
    </div>
</section>
