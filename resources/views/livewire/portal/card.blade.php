<div class="mx-auto max-w-sm space-y-4" x-data x-init="if ('wakeLock' in navigator) navigator.wakeLock.request('screen').catch(() => {})">
    <div class="overflow-hidden rounded-3xl bg-gradient-to-br from-brand-600 via-brand-800 to-brand-950 text-white shadow-xl">
        <div class="flex items-center justify-between px-6 pt-6">
            <x-logo light size="size-10" name-class="!text-sm" />
            <span class="rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold tracking-wider uppercase">Socio</span>
        </div>
        <div class="flex items-center gap-4 px-6 pt-6">
            @if ($member->photoUrl())
                <img src="{{ $member->photoUrl() }}" alt="" class="size-20 rounded-2xl object-cover ring-2 ring-white/40">
            @else
                <span class="grid size-20 place-items-center rounded-2xl bg-white/15 font-display text-2xl font-bold">{{ $member->initials() }}</span>
            @endif
            <div class="min-w-0">
                <p class="truncate font-display text-xl font-bold">{{ $member->fullName() }}</p>
                <p class="text-sm text-white/70">{{ $member->document_type }} {{ $member->document_number }}</p>
                <p class="text-sm text-white/70">{{ $member->category->name }}</p>
            </div>
        </div>
        <div class="mx-6 mt-6 rounded-2xl bg-white p-4">
            <div class="mx-auto aspect-square w-full max-w-[240px] text-slate-900 [&_svg]:size-full">{!! $qr !!}</div>
        </div>
        <div class="flex items-end justify-between px-6 pt-4 pb-6">
            <div>
                <p class="text-[11px] tracking-wider text-white/60 uppercase">N° de socio</p>
                <p class="font-display text-2xl font-bold tracking-wider">{{ $member->member_number ?? '—' }}</p>
            </div>
            <div class="text-right">
                <p class="text-[11px] tracking-wider text-white/60 uppercase">Socio desde</p>
                <p class="font-semibold">{{ $member->admission_date?->format('m/Y') ?? '—' }}</p>
            </div>
        </div>
    </div>

    <div @class(['flex items-center gap-3 rounded-xl p-4 text-sm', 'bg-emerald-50 text-emerald-800' => $member->isActive() && ! $overdue, 'bg-amber-50 text-amber-800' => $member->isActive() && $overdue, 'bg-red-50 text-red-800' => ! $member->isActive()])>
        <x-icon :name="$member->isActive() ? 'check-circle' : 'x-circle'" class="size-6 shrink-0" />
        <p>
            Estado: <strong>{{ $member->status->label() }}</strong>
            @if ($overdue) · {{ $overdue }} {{ $overdue === 1 ? 'cuota vencida' : 'cuotas vencidas' }} @endif
        </p>
    </div>
    <p class="text-center text-xs text-slate-500">Presentá este código en el ingreso al club. Agregá el portal a la pantalla de inicio para tenerlo siempre a mano.</p>
</div>
