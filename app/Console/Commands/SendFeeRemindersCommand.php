<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsForEachOrganization;
use App\Enums\FeeStatus;
use App\Models\Fee;
use App\Models\Member;
use App\Models\Organization;
use App\Notifications\FeeReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('club:recordatorios')]
#[Description('Envía recordatorios por correo de cuotas próximas a vencer y vencidas (los lunes)')]
class SendFeeRemindersCommand extends Command
{
    use RunsForEachOrganization;

    public function handle(): int
    {
        $this->forEachOrganization(fn (Organization $org) => $this->remind($org));

        return self::SUCCESS;
    }

    private function remind(Organization $org): void
    {
        if (! setting('club.reminders_enabled', true)) {
            $this->warn("{$org->name}: recordatorios deshabilitados.");

            return;
        }

        $daysBefore = (int) setting('club.reminder_days_before', 3);
        $sent = 0;

        Member::active()
            ->whereNotNull('email')
            ->whereHas('fees', fn ($q) => $this->dueFees($q, $daysBefore))
            ->with(['fees' => fn ($q) => $this->dueFees($q, $daysBefore)->orderBy('due_date')])
            ->chunkById(200, function ($members) use (&$sent) {
                foreach ($members as $member) {
                    $fees = $member->fees->map(fn (Fee $fee) => [
                        'concept' => $fee->concept,
                        'balance' => $fee->balance(),
                        'due_date' => $fee->due_date->format('d/m/Y'),
                        'overdue' => $fee->status === FeeStatus::Overdue,
                    ]);

                    $total = $fees->reduce(fn ($carry, $fee) => bcadd($carry, $fee['balance'], 2), '0');

                    $member->notify(new FeeReminderNotification($fees, $total));
                    $sent++;
                }
            });

        $this->info("{$org->name}: se enviaron {$sent} recordatorios.");
    }

    /**
     * Cargos que vencen exactamente dentro de N días, o vencidos (estos últimos solo se recuerdan los lunes).
     */
    private function dueFees($query, int $daysBefore)
    {
        return $query->where(function ($q) use ($daysBefore) {
            $q->whereIn('status', [FeeStatus::Pending->value, FeeStatus::Partial->value])
                ->whereDate('due_date', today()->addDays($daysBefore));

            if (today()->isMonday()) {
                $q->orWhere('status', FeeStatus::Overdue->value);
            }
        });
    }
}
