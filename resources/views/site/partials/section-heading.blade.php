<div class="mx-auto mb-12 max-w-2xl text-center">
    @if ($section->title)
        <h2 class="font-display text-3xl font-bold tracking-tight {{ $light ?? false ? 'text-white' : 'text-slate-900' }} sm:text-4xl">{{ $section->title }}</h2>
    @endif
    <div class="mx-auto mt-4 h-1 w-14 rounded-full bg-accent-500"></div>
    @if ($section->subtitle)
        <p class="mt-4 text-lg {{ $light ?? false ? 'text-white/70' : 'text-slate-600' }}">{{ $section->subtitle }}</p>
    @endif
</div>
