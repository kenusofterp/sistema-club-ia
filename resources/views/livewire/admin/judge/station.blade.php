<div x-data="{ timer: null, init() { this.timer = setInterval(() => { if (window.Echo?.connector?.pusher?.connection?.state !== 'connected') $wire.$refresh() }, 4000) }, destroy() { clearInterval(this.timer) } }">
    @if (! $assignment)
        <div class="card mt-6 p-6 text-center">
            <x-icon name="clipboard" class="mx-auto size-10 text-slate-300" />
            <h1 class="mt-3 font-display text-lg font-bold text-slate-900">No tenés un puesto asignado</h1>
            <p class="mt-1 text-sm text-slate-500">Cuando la organización te asigne a un puesto de una competencia en curso, vas a verlo acá. La pantalla se actualiza sola.</p>
        </div>
    @else
        <div class="mb-4 flex items-center justify-between gap-2">
            <div class="min-w-0">
                <p class="truncate text-xs font-semibold tracking-wider text-brand-700 uppercase">{{ $station->competition->name }}</p>
                <h1 class="font-display text-xl font-bold text-slate-900">{{ $station->name }} <span class="font-normal text-slate-500">· Juez {{ $assignment->seat }}</span></h1>
            </div>
            <x-badge color="brand">{{ $station->competitionExercise->name() }}</x-badge>
        </div>

        @if (! $current)
            <div class="card p-6 text-center">
                <x-icon name="check-circle" class="mx-auto size-10 text-emerald-500" />
                <p class="mt-3 font-semibold text-slate-900">No hay participantes en espera</p>
                <p class="text-sm text-slate-500">Cuando la organización agregue la siguiente, aparece acá.</p>
            </div>
        @else
            <div class="card overflow-hidden">
                <div class="flex items-center gap-4 bg-brand-50 p-4">
                    <span class="grid size-16 shrink-0 place-items-center rounded-2xl bg-brand-700 font-display text-2xl font-extrabold text-white tabular-nums">{{ $current->entry->bib_number }}</span>
                    <div class="min-w-0">
                        <p class="truncate font-display text-xl font-bold text-slate-900">{{ $current->entry->competitor->fullName() }}</p>
                        <p class="truncate text-sm text-slate-600">{{ $current->entry->competitor->institution }}</p>
                        <p class="text-xs text-slate-500">{{ $current->entry->categoryLabel() }}</p>
                    </div>
                </div>

                @if ($mySheet)
                    {{-- Ya confirmó: ve solo su nota, en solo lectura. --}}
                    <div class="p-4">
                        <div class="mb-3 flex items-center gap-2 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">
                            <x-icon name="check-circle" class="size-5" />
                            <span>Nota confirmada. Esperando a los otros jueces ({{ count($confirmedSeats) }}/3).</span>
                        </div>
                        <dl class="divide-y divide-slate-100 text-sm">
                            @foreach ($criteria as $criterion)
                                @php $value = $mySheet->scores->firstWhere('competition_criterion_id', $criterion->id)?->value; @endphp
                                <div class="flex justify-between py-2">
                                    <dt class="text-slate-600">{{ $criterion->name }}</dt>
                                    <dd class="font-semibold tabular-nums">{{ $criterion->type === \App\Enums\CriterionType::YesNo ? ((float) $value ? 'Sí' : 'No') : rtrim(rtrim(number_format((float) $value, 3, ',', ''), '0'), ',') }}</dd>
                                </div>
                            @endforeach
                            <div class="flex justify-between py-2 text-base font-bold">
                                <dt>Mi total</dt><dd class="tabular-nums">{{ number_format((float) $mySheet->total, 3, ',', '.') }}</dd>
                            </div>
                        </dl>
                    </div>
                @else
                    @php
                        $key = 'juez-'.auth()->id().'-ev-'.$current->id;
                        $defaults = $criteria->mapWithKeys(fn ($c) => [$c->id => match ($c->type) {
                            \App\Enums\CriterionType::YesNo => false,
                            \App\Enums\CriterionType::Deduction => '0',
                            default => '',
                        }])->all();
                        $spec = $criteria->map(fn ($c) => ['id' => $c->id, 'type' => $c->type->value, 'min' => (float) $c->min, 'max' => (float) $c->max, 'decimals' => $c->decimals, 'weight' => (float) $c->weight, 'name' => $c->name])->values();
                    @endphp
                    <form class="p-4" wire:key="form-{{ $current->id }}"
                          x-data="{
                              values: $persist(@js($defaults)).as('{{ $key }}'),
                              pending: $persist(false).as('{{ $key }}-pendiente'),
                              criteria: @js($spec),
                              state: 'idle',
                              message: '',
                              attempts: 0,
                              num(v) { const n = parseFloat(String(v ?? '').replace(',', '.')); return isNaN(n) ? null : n },
                              invalid(c) {
                                  if (c.type === 'si_no') return false;
                                  const raw = String(this.values[c.id] ?? '').replace(',', '.').trim();
                                  if (raw === '') return c.type !== 'deduccion' ? 'Completá este valor' : false;
                                  const n = this.num(raw);
                                  if (n === null) return 'Ingresá un número';
                                  if (n < c.min || n > c.max) return 'Entre ' + c.min + ' y ' + c.max;
                                  const dec = (raw.split('.')[1] ?? '').replace(/0+$/, '').length;
                                  if (dec > c.decimals) return c.decimals === 0 ? 'Sin decimales' : 'Hasta ' + c.decimals + ' decimales';
                                  return false;
                              },
                              total() {
                                  let t = 0;
                                  for (const c of this.criteria) {
                                      const v = c.type === 'si_no' ? (this.values[c.id] ? 1 : 0) : (this.num(this.values[c.id]) ?? 0);
                                      t += c.type === 'puntaje' ? v * c.weight : c.type === 'deduccion' ? -v * c.weight : (v ? c.weight : 0);
                                  }
                                  return Math.max(0, Math.trunc(t * 1000) / 1000).toFixed(3).replace('.', ',');
                              },
                              async send() {
                                  if (this.criteria.some(c => this.invalid(c))) { this.state = 'error'; this.message = 'Revisá los valores marcados.'; return; }
                                  this.pending = true;
                                  this.state = 'sending';
                                  // Si no hay respuesta en 10 s se reintenta: confirmar dos veces el mismo valor es seguro.
                                  const timeout = new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 10000));
                                  try {
                                      const result = await Promise.race([$wire.confirm({{ $current->id }}, this.values), timeout]);
                                      this.pending = false;
                                      if (result?.ok) { this.state = 'done'; localStorage.removeItem('{{ $key }}'); localStorage.removeItem('{{ $key }}-pendiente'); }
                                      else { this.state = 'error'; this.message = result?.message ?? 'No se pudo confirmar.'; }
                                  } catch (e) {
                                      this.state = 'queued';
                                      this.attempts++;
                                      setTimeout(() => { if (this.pending) this.send() }, Math.min(30000, 2000 * this.attempts));
                                  }
                              },
                              init() {
                                  window.addEventListener('online', () => { if (this.pending) this.send() });
                                  if (this.pending) this.send();
                              },
                          }"
                          x-on:submit.prevent="if (confirm('¿Confirmar tu nota de ' + total() + '? Después solo se puede cambiar con autorización.')) send()">
                        <div class="space-y-3">
                            <template x-for="c in criteria" :key="c.id">
                                <div>
                                    <template x-if="c.type === 'si_no'">
                                        <label class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 p-3">
                                            <span class="font-medium text-slate-800" x-text="c.name"></span>
                                            <span class="flex items-center gap-2 text-sm">
                                                <span class="text-slate-500" x-text="values[c.id] ? 'Sí' : 'No'"></span>
                                                <input type="checkbox" x-model="values[c.id]" class="size-7 rounded-lg border-slate-300 text-brand-600 focus:ring-brand-500">
                                            </span>
                                        </label>
                                    </template>
                                    <template x-if="c.type !== 'si_no'">
                                        <label class="block">
                                            <span class="flex items-baseline justify-between gap-2">
                                                <span class="font-medium text-slate-800" x-text="c.name"></span>
                                                <span class="text-xs text-slate-500" x-text="(c.type === 'deduccion' ? 'Deducción · ' : '') + c.min + ' a ' + c.max"></span>
                                            </span>
                                            <input type="text" inputmode="decimal" autocomplete="off" x-model="values[c.id]"
                                                   :class="invalid(c) && values[c.id] !== '' ? 'border-red-400 ring-red-400' : ''"
                                                   class="form-input mt-1 py-3 text-center font-display text-2xl font-bold tabular-nums">
                                            <span class="form-error" x-show="invalid(c) && values[c.id] !== ''" x-text="invalid(c)"></span>
                                        </label>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <div class="mt-4 flex items-center justify-between rounded-xl bg-slate-900 px-4 py-3 text-white">
                            <span class="text-sm text-white/70">Mi total</span>
                            <span class="font-display text-3xl font-extrabold tabular-nums" x-text="total()"></span>
                        </div>

                        <p x-show="state === 'error'" x-cloak class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700" x-text="message"></p>
                        <p x-show="state === 'queued'" x-cloak class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                            Sin conexión: tu nota quedó guardada en el teléfono y se envía sola apenas vuelva la señal.
                        </p>

                        <button type="submit" class="btn-primary mt-4 w-full py-4 text-lg" :disabled="state === 'sending' || state === 'done'">
                            <span x-show="state !== 'sending'">Confirmar nota</span>
                            <span x-show="state === 'sending'" x-cloak>Enviando…</span>
                        </button>
                    </form>
                @endif
            </div>
        @endif

        @if ($recent->isNotEmpty())
            <h2 class="mt-6 mb-2 text-sm font-semibold tracking-wide text-slate-500 uppercase">Mis notas anteriores</h2>
            <ul class="card divide-y divide-slate-100 text-sm">
                @foreach ($recent as $sheet)
                    <li class="flex items-center gap-3 p-3" wire:key="sheet-{{ $sheet->id }}">
                        <span class="w-8 font-display font-bold text-slate-500 tabular-nums">{{ $sheet->evaluation->entry->bib_number }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-slate-800">{{ $sheet->evaluation->entry->competitor->fullName() }}</span>
                            <span class="text-xs text-slate-500">
                                Mi total {{ number_format((float) $sheet->total, 3, ',', '.') }}
                                @if ($sheet->evaluation->isPublished()) · Puntaje {{ number_format((float) $sheet->evaluation->score, 3, ',', '.') }}@endif
                            </span>
                        </span>
                        @if (in_array($sheet->id, $pendingChanges))
                            <x-badge color="yellow">Cambio pedido</x-badge>
                        @else
                            <button type="button" wire:click="askChange({{ $sheet->id }})" class="btn-ghost btn-sm">Pedir cambio</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endif

    <x-modal wire:model="showChange" title="Pedir modificación de nota" max-width="max-w-md">
        <form wire:submit="requestChange" id="change-form" class="space-y-3">
            @foreach ($changeCriteria as $criterion)
                @if ($criterion->type === \App\Enums\CriterionType::YesNo)
                    <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 text-sm">
                        <span class="font-medium text-slate-800">{{ $criterion->name }}</span>
                        <select wire:model="changeValues.{{ $criterion->id }}" class="form-input w-24 py-1">
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                    </label>
                @else
                    <x-field :label="$criterion->name.' ('.rtrim(rtrim($criterion->min, '0'), '.').' a '.rtrim(rtrim($criterion->max, '0'), '.').')'">
                        <input type="text" inputmode="decimal" wire:model="changeValues.{{ $criterion->id }}" class="form-input text-lg font-semibold tabular-nums">
                    </x-field>
                @endif
            @endforeach
            <x-field label="Motivo" for="change-reason" error="changeReason" required>
                <textarea id="change-reason" wire:model="changeReason" rows="3" class="form-input" placeholder="Ej.: me equivoqué al tipear la ejecución"></textarea>
            </x-field>
            <p class="text-xs text-slate-500">La nota no cambia hasta que la autorice otra persona (juez principal u organización).</p>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="change-form" class="btn-primary">Enviar pedido</button>
        </x-slot:footer>
    </x-modal>
</div>
