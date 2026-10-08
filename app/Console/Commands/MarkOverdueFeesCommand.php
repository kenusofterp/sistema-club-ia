<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsForEachOrganization;
use App\Models\Organization;
use App\Services\FeeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('club:marcar-vencidas')]
#[Description('Marca como vencidas las cuotas impagas y aplica el recargo por mora de cada entidad')]
class MarkOverdueFeesCommand extends Command
{
    use RunsForEachOrganization;

    public function handle(FeeService $fees): int
    {
        $this->forEachOrganization(function (Organization $org) use ($fees) {
            $count = $fees->markOverdue();

            if ($count > 0) {
                activity('fees')->event('overdue')->withProperties(['count' => $count])->log("{$count} cargos marcados como vencidos");
            }

            $this->info("{$org->name}: {$count} cargos marcados como vencidos.");
        });

        return self::SUCCESS;
    }
}
