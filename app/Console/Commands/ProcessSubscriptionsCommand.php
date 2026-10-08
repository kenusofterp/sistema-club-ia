<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsForEachOrganization;
use App\Models\Organization;
use App\Services\SubscriptionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('club:procesar-planes')]
#[Description('Vence planes de gimnasio, genera renovaciones automáticas y cancela altas impagas (en cada entidad con gimnasio)')]
class ProcessSubscriptionsCommand extends Command
{
    use RunsForEachOrganization;

    public function handle(SubscriptionService $subscriptions): int
    {
        $this->forEachOrganization(function (Organization $org) use ($subscriptions) {
            if (! $org->usesGym()) {
                return;
            }

            $result = $subscriptions->processDaily();

            if (array_sum($result) > 0) {
                activity('subscriptions')->event('processed')->withProperties($result)->log('Proceso diario de planes');
            }

            $this->info("{$org->name}: vencidos {$result['expired']} · renovados {$result['renewed']} · cancelados por falta de pago {$result['cancelled']}");
        });

        return self::SUCCESS;
    }
}
