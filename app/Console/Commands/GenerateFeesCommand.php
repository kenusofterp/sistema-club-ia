<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMemberFeesJob;
use App\Models\Member;
use App\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('club:generar-cuotas
    {--periodo= : Período AAAA-MM (por defecto el mes actual)}
    {--entidad= : Identificador (slug) de una entidad; por defecto todas}
    {--forzar : Generar aunque hoy no sea el día configurado en la entidad}
    {--sync : Procesar sin usar la cola}')]
#[Description('Genera las cuotas sociales y de actividades del período para los socios activos de cada entidad')]
class GenerateFeesCommand extends Command
{
    public function handle(): int
    {
        $period = $this->option('periodo')
            ? Carbon::createFromFormat('Y-m', $this->option('periodo'))->startOfMonth()
            : today()->startOfMonth();

        // Con un período explícito se asume ejecución manual.
        $force = $this->option('forzar') || $this->option('periodo');

        $organizations = Organization::query()->where('is_active', true)
            ->when($this->option('entidad'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get();

        foreach ($organizations as $organization) {
            Organization::runFor($organization, function (Organization $org) use ($period, $force) {
                if (! $force && today()->day !== (int) setting('club.fee_generation_day', 1)) {
                    return;
                }

                $count = 0;
                Member::active()->select('id')->chunkById(500, function ($members) use ($period, &$count) {
                    foreach ($members as $member) {
                        $job = new GenerateMemberFeesJob($member->id, $period->toDateString());
                        $this->option('sync') ? dispatch_sync($job) : dispatch($job);
                        $count++;
                    }
                });

                activity('fees')->event('generated')
                    ->withProperties(['period' => $period->format('Y-m'), 'members' => $count])
                    ->log('Generación de cuotas '.$period->format('m/Y'));

                $this->info("{$org->name}: {$count} socios procesados para {$period->format('m/Y')}.");
            });
        }

        return self::SUCCESS;
    }
}
