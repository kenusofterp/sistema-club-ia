<div>
    <x-page-header :title="'Hola, '.explode(' ', auth()->user()->name)[0]" :subtitle="ucfirst(now()->translatedFormat('l j \d\e F \d\e Y'))">
        <x-slot:actions>
            <a href="{{ route('admin.help') }}" wire:navigate class="btn-secondary"><x-icon name="help" class="size-4" /> Ayuda</a>
            @can('pagos.registrar')
                <a href="{{ route('admin.payments.create') }}" wire:navigate class="btn-primary"><x-icon name="banknotes" class="size-4" /> Registrar pago</a>
            @endcan
            @can('socios.crear')
                <a href="{{ route('admin.members.create') }}" wire:navigate class="btn-secondary"><x-icon name="user-plus" class="size-4" /> Nuevo socio</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (! $canSee)
        <div class="card p-8">
            <p class="text-slate-600">Bienvenido/a al sistema. Usá el menú lateral para acceder a las secciones habilitadas para tu rol.</p>
        </div>
    @else
        @if ($activeMembers === 0 && $pendingMembers === 0)
            <div class="mb-4 flex flex-col gap-4 rounded-xl border border-brand-200 bg-brand-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <x-icon name="sparkles" class="size-6 shrink-0 text-brand-600" />
                    <div>
                        <p class="font-semibold text-brand-900">¿Recién empezás? Seguí los primeros pasos</p>
                        <p class="text-sm text-brand-800">Configurá el club, revisá las categorías y cargá tu primer socio. El manual te guía paso a paso con ejemplos.</p>
                    </div>
                </div>
                <a href="{{ route('admin.help') }}#primeros-pasos" wire:navigate class="btn-primary shrink-0"><x-icon name="help" class="size-4" /> Abrir el manual</a>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Socios activos" :value="number_format($activeMembers, 0, ',', '.')" icon="users"
                         :hint="$pendingMembers ? $pendingMembers.' solicitudes pendientes' : 'Sin solicitudes pendientes'"
                         :href="auth()->user()->can('socios.ver') ? route('admin.members.index') : null" />
            <x-stat-card label="Recaudación del mes" :value="money($incomeThisMonth)" icon="banknotes" tone="sky"
                         :hint="$incomeDelta !== null ? ($incomeDelta >= 0 ? '▲ ' : '▼ ').abs($incomeDelta).'% vs. mismo período del mes anterior' : null"
                         :href="auth()->user()->can('pagos.ver') ? route('admin.payments.index') : null" />
            <x-stat-card label="Deuda vencida" :value="money($overdueTotal)" icon="warning" tone="red"
                         :hint="$overdueMembers.' socios con cuotas vencidas'"
                         :href="auth()->user()->can('cuotas.ver') ? route('admin.fees', ['status' => 'vencida']) : null" />
            <x-stat-card label="Inscripciones activas" :value="number_format($activeEnrollments, 0, ',', '.')" icon="trophy" tone="amber"
                         :hint="$todayReservations->count().' reservas para hoy'" />
        </div>

        @if ($gymStats)
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card label="Planes vigentes" :value="$gymStats['active']" icon="id-card"
                             :href="auth()->user()->can('suscripciones.gestionar') ? route('admin.gym.subscriptions') : null" />
                <x-stat-card label="Planes pendientes de pago" :value="$gymStats['pending']" icon="banknotes" tone="amber"
                             :href="auth()->user()->can('suscripciones.gestionar') ? route('admin.gym.subscriptions', ['status' => 'pendiente']) : null" />
                <x-stat-card label="Planes que vencen en 7 días" :value="$gymStats['expiring']" icon="clock" tone="red"
                             :href="auth()->user()->can('suscripciones.gestionar') ? route('admin.gym.subscriptions', ['por-vencer' => 1]) : null" />
                <x-stat-card label="Ingresos hoy" :value="$gymStats['todayVisits']" icon="qr" tone="sky"
                             :href="auth()->user()->can('acceso.registrar') ? route('admin.access') : null" />
            </div>
        @endif

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            {{-- Recaudación mensual: una sola serie, barras con tooltip por mes --}}
            <div class="card p-6 xl:col-span-2">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-slate-900">Recaudación mensual</h2>
                        <p class="text-sm text-slate-500">Pagos confirmados de los últimos 12 meses</p>
                    </div>
                    @can('reportes.ver')
                        <a href="{{ route('admin.export', 'pagos') }}" class="btn-ghost btn-sm"><x-icon name="download" class="size-4" /> CSV</a>
                    @endcan
                </div>

                <div class="relative mt-6 h-64" x-data="{ hover: null }" role="img" aria-label="Gráfico de barras de recaudación mensual">
                    {{-- Grilla tenue --}}
                    <div class="pointer-events-none absolute inset-0 bottom-6 flex flex-col justify-between">
                        @foreach ([1, 0.75, 0.5, 0.25, 0] as $tick)
                            <div class="flex items-center gap-2">
                                <span class="w-14 shrink-0 text-right text-[11px] text-slate-400 tabular-nums">{{ $chartMax * $tick >= 1000000 ? number_format($chartMax * $tick / 1000000, 1, ',', '.').'M' : number_format($chartMax * $tick / 1000, 0, ',', '.').'k' }}</span>
                                <div class="h-px flex-1 {{ $tick === 0 ? 'bg-slate-300' : 'bg-slate-100' }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="absolute inset-0 bottom-6 left-16 flex items-end gap-[2px]">
                        @foreach ($chart as $i => $bar)
                            <div class="group relative flex h-full flex-1 cursor-default items-end justify-center"
                                 x-on:mouseenter="hover = {{ $i }}" x-on:mouseleave="hover = null">
                                <div class="w-full max-w-10 rounded-t-[4px] transition-colors {{ $loop->last ? 'bg-brand-600' : 'bg-brand-400' }}"
                                     :class="hover === {{ $i }} && 'bg-brand-700'"
                                     style="height: {{ $bar['value'] > 0 ? max(1, $bar['value'] / $chartMax * 100) : 0 }}%"></div>
                                <div x-show="hover === {{ $i }}" x-cloak
                                     class="absolute bottom-full z-10 mb-2 rounded-lg bg-slate-900 px-3 py-2 text-xs whitespace-nowrap text-white shadow-lg"
                                     style="bottom: calc({{ $bar['value'] / $chartMax * 100 }}% + 8px)">
                                    <p class="text-white/70">{{ $bar['full'] }}</p>
                                    <p class="font-semibold tabular-nums">{{ money($bar['value']) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="absolute inset-x-0 bottom-0 left-16 flex gap-[2px]">
                        @foreach ($chart as $bar)
                            <span class="flex-1 text-center text-[11px] text-slate-500">{{ $bar['label'] }}</span>
                        @endforeach
                    </div>
                </div>
                <table class="sr-only">
                    <caption>Recaudación mensual</caption>
                    <tbody>
                        @foreach ($chart as $bar)
                            <tr><th>{{ $bar['full'] }}</th><td>{{ money($bar['value']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Socios por categoría --}}
            <div class="card p-6">
                <h2 class="font-semibold text-slate-900">Socios activos por categoría</h2>
                <ul class="mt-5 space-y-4">
                    @forelse ($membersByCategory as $row)
                        <li>
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="text-slate-700">{{ $row->name }}</span>
                                <span class="font-medium text-slate-900 tabular-nums">{{ $row->total }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100">
                                <div class="h-2 rounded-full bg-brand-500" style="width: {{ $activeMembers ? $row->total / $activeMembers * 100 : 0 }}%"></div>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">Todavía no hay socios activos.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="font-semibold text-slate-900">Últimos pagos</h2>
                    @can('pagos.ver')<a href="{{ route('admin.payments.index') }}" wire:navigate class="text-sm font-medium text-brand-700">Ver todos</a>@endcan
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($latestPayments as $payment)
                        <li class="flex items-center justify-between gap-3 px-6 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800">{{ $payment->member?->fullName() }}</p>
                                <p class="text-xs text-slate-500">{{ $payment->receipt_number }} · {{ $payment->payment_date->format('d/m') }} · {{ $payment->method->label() }}</p>
                            </div>
                            <span class="text-sm font-semibold text-slate-900 tabular-nums">{{ money($payment->amount) }}</span>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-sm text-slate-500">Sin pagos registrados.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="font-semibold text-slate-900">Solicitudes de asociación</h2>
                    @can('socios.ver')<a href="{{ route('admin.members.index', ['status' => 'pendiente']) }}" wire:navigate class="text-sm font-medium text-brand-700">Ver todas</a>@endcan
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($pendingList as $member)
                        <li>
                            <a href="{{ route('admin.members.show', $member) }}" wire:navigate class="flex items-center justify-between gap-3 px-6 py-3 hover:bg-slate-50">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-800">{{ $member->fullName() }}</p>
                                    <p class="text-xs text-slate-500">{{ $member->category->name }} · {{ $member->created_at->diffForHumans() }}</p>
                                </div>
                                <x-icon name="chevron-right" class="size-4 text-slate-400" />
                            </a>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-sm text-slate-500">No hay solicitudes pendientes.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="font-semibold text-slate-900">Reservas de hoy</h2>
                    @can('reservas.ver')<a href="{{ route('admin.reservations') }}" wire:navigate class="text-sm font-medium text-brand-700">Ver agenda</a>@endcan
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($todayReservations as $reservation)
                        <li class="flex items-center justify-between gap-3 px-6 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800">{{ $reservation->facility->name }}</p>
                                <p class="text-xs text-slate-500">{{ $reservation->member->fullName() }}</p>
                            </div>
                            <span class="text-sm text-slate-600 tabular-nums">{{ $reservation->timeRange() }}</span>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-sm text-slate-500">No hay reservas para hoy.</li>
                    @endforelse
                </ul>
                @if ($unreadMessages)
                    <a href="{{ route('admin.messages') }}" wire:navigate class="flex items-center gap-2 border-t border-slate-100 px-6 py-3 text-sm font-medium text-brand-700 hover:bg-slate-50">
                        <x-icon name="inbox" class="size-4" /> {{ $unreadMessages }} mensajes sin leer
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
