<div class="flex grow flex-col overflow-y-auto bg-brand-950 px-4 pb-4">
    <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex h-16 shrink-0 items-center px-2">
        <x-logo light size="size-9" name-class="!text-base" />
    </a>
    <nav class="flex flex-1 flex-col">
        <ul role="list" class="flex flex-1 flex-col gap-y-5">
            @foreach ($menu as $group)
                <li>
                    @if ($group['title'])
                        <div class="mb-1 px-2 text-[11px] font-semibold tracking-wider text-white/40 uppercase">{{ $group['title'] }}</div>
                    @endif
                    <ul role="list" class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            <li>
                                <a href="{{ route($item['route']) }}" wire:navigate
                                   @class([
                                       'group flex items-center gap-x-3 rounded-lg px-2 py-2 text-sm font-medium transition',
                                       'bg-white/10 text-white' => $item['active'],
                                       'text-white/70 hover:bg-white/5 hover:text-white' => ! $item['active'],
                                   ])>
                                    <x-icon :name="$item['icon']" @class(['size-5 shrink-0', 'text-accent-400' => $item['active'], 'text-white/50 group-hover:text-white' => ! $item['active']]) />
                                    <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                    @if ($item['badge'])
                                        <span class="rounded-full bg-accent-500 px-2 py-0.5 text-xs font-semibold text-slate-900">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    </nav>
    <p class="mt-6 px-2 text-xs text-white/30">{{ setting('site.name') }} · v1.0</p>
</div>
