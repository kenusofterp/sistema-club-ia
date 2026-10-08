<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Models\Member;
use App\Models\Plan;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Demo de gimnasio: asigna planes a parte de los socios y paga la mayoría.
 * Uso: php artisan db:seed --class=DemoGymSeeder (también lo invoca DemoSeeder).
 */
class DemoGymSeeder extends Seeder
{
    public function run(SubscriptionService $subscriptions, PaymentService $payments): void
    {
        Notification::fake();
        $plans = Plan::active()->get();
        if ($plans->isEmpty()) {
            return;
        }

        $members = Member::active()->inRandomOrder()->limit(25)->get();
        foreach ($members as $i => $member) {
            try {
                $subscription = $subscriptions->subscribe($member, $plans[$i % $plans->count()]);
                if ($i % 5 !== 0) {
                    $fee = $subscription->fees()->first();
                    $payments->register($member, $fee->balance(), PaymentMethod::Cash, [$fee->id]);
                }
            } catch (Throwable) {
                // regla de negocio (p. ej. plan duplicado): se ignora en la demo
            }
        }

        $this->command?->info('Demo gimnasio: '.$members->count().' socios con plan.');
    }
}
