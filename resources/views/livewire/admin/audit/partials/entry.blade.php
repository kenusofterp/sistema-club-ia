@php
    $changes = $audit->attribute_changes ?? collect();
    $new = $changes['attributes'] ?? [];
    $old = $changes['old'] ?? [];
    $events = ['created' => ['Alta', 'green'], 'updated' => ['Modificación', 'blue'], 'deleted' => ['Eliminación', 'red'], 'login' => ['Ingreso', 'green'], 'logout' => ['Salida', 'gray'], 'failed' => ['Intento fallido', 'red']];
    [$eventLabel, $eventColor] = $events[$audit->event] ?? [ucfirst((string) $audit->event ?: 'Evento'), 'gray'];
    $format = fn ($v) => is_bool($v) ? ($v ? 'Sí' : 'No') : (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) ($v ?? '—'));
@endphp
<div class="px-5 py-4" x-data="{ open: false }">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <x-badge :color="$eventColor">{{ $eventLabel }}</x-badge>
                <span class="text-sm font-medium text-slate-800">{{ $audit->description }}</span>
                @if ($audit->log_name)
                    <span class="text-xs text-slate-400">{{ $audit->log_name }}@if ($audit->subject_id) #{{ $audit->subject_id }}@endif</span>
                @endif
            </div>
            <p class="mt-1 text-xs text-slate-500">
                {{ $audit->created_at->format('d/m/Y H:i:s') }} · {{ $audit->causer?->name ?? 'Sistema' }}
                @if ($ip = $audit->properties['ip'] ?? null) · IP {{ $ip }} @endif
            </p>
        </div>
        @if ($new || ($audit->properties && count($audit->properties)))
            <button type="button" x-on:click="open = !open" class="btn-ghost btn-sm"><span x-text="open ? 'Ocultar' : 'Detalle'"></span></button>
        @endif
    </div>
    <div x-show="open" x-cloak class="mt-3 overflow-x-auto rounded-lg bg-slate-50 p-3 text-xs">
        @if ($new)
            <table class="w-full">
                <thead><tr class="text-left text-slate-500"><th class="py-1 pr-4 font-medium">Campo</th>@if ($old)<th class="py-1 pr-4 font-medium">Antes</th>@endif<th class="py-1 font-medium">{{ $old ? 'Después' : 'Valor' }}</th></tr></thead>
                <tbody>
                    @foreach ($new as $field => $value)
                        <tr class="border-t border-slate-200">
                            <td class="py-1 pr-4 font-mono text-slate-600">{{ $field }}</td>
                            @if ($old)<td class="py-1 pr-4 text-red-700">{{ \Illuminate\Support\Str::limit($format($old[$field] ?? null), 80) }}</td>@endif
                            <td class="py-1 text-emerald-700">{{ \Illuminate\Support\Str::limit($format($value), 80) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @if ($audit->properties && count($audit->properties))
            <pre class="mt-2 whitespace-pre-wrap text-slate-600">{{ json_encode($audit->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </div>
</div>
