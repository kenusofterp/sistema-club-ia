<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Models\Activity;
use App\Models\Facility;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\FeeService;
use App\Services\OrganizationProvisioner;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Services\SubscriptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Demo multi-entidad: dos clubes y un gimnasio, con socios que pertenecen a más de una entidad
 * y personal con roles distintos en cada una.
 * Uso: php artisan db:seed --class=DemoSeeder (después de migrate:fresh --seed; crea los roles sugeridos)
 */
class DemoSeeder extends Seeder
{
    public function run(OrganizationProvisioner $provisioner): void
    {
        Notification::fake();

        $this->call(RolesAndPermissionsSeeder::class);

        $principal = Organization::where('is_default', true)->first()
            ?? Organization::firstOrCreate(['slug' => 'principal'], ['name' => config('club.default_organization.name'), 'type' => config('club.default_organization.type'), 'is_default' => true]);
        $atletico = Organization::firstOrCreate(['slug' => 'atletico'], ['name' => 'Club Atlético del Sur', 'type' => 'club']);
        $gym = Organization::firstOrCreate(['slug' => 'gimnasio'], ['name' => 'Sidkenu Gym', 'type' => 'gimnasio']);
        foreach ([$principal, $atletico, $gym] as $org) {
            $provisioner->provision($org);
            // Las entidades nuevas arrancan vacías: la demo agrega el contenido de ejemplo.
            Organization::runFor($org, function (Organization $org) {
                $this->call(SiteContentSeeder::class);
                if ($org->usesClub()) {
                    $this->call(ClubBaseSeeder::class);
                }
                if ($org->usesGym()) {
                    $this->call(GymSeeder::class);
                }
            });
        }

        // Personas que serán socias en varias entidades (mismo documento).
        $people = Member::factory()->count(12)->make(['member_category_id' => null])
            ->map(fn (Member $m) => [...$m->only(['first_name', 'last_name', 'document_number', 'gender', 'email', 'phone', 'address', 'city']), 'birth_date' => $m->birth_date->toDateString()]);

        $principalMembers = Organization::runFor($principal, fn () => $this->seedClub(45, $people->take(10)));
        Organization::runFor($atletico, fn () => $this->seedClub(25, $people->slice(5, 5)));
        Organization::runFor($gym, fn () => $this->seedGym(20, $people->slice(0, 8)));

        $this->seedDemoAccounts($principal, $atletico, $gym, $principalMembers->first());

        $this->command?->info('Demo: 3 entidades. Socio en las tres: socio@demo.com / Socio1234 · Personal: tesoreria@demo.com y recepcion@demo.com / Demo1234');
    }

    /** @param  Collection<int, array<string, mixed>>  $shared */
    private function seedClub(int $count, Collection $shared): Collection
    {
        $fees = app(FeeService::class);
        $payments = app(PaymentService::class);
        $enrollments = app(EnrollmentService::class);
        $reservations = app(ReservationService::class);
        $admin = User::where('is_super_admin', true)->first();

        $adult = MemberCategory::where('name', 'Activo')->firstOrFail();
        $base = ['member_category_id' => $adult->id, 'admission_date' => today()->subMonths(8)];

        $members = Member::factory()->count($count)->withNumber()->create($base);
        foreach ($shared->values() as $i => $person) {
            $members->push(Member::factory()->create([...$base, ...$person, 'member_number' => str_pad((string) ($count + $i + 1), 5, '0', STR_PAD_LEFT)]));
        }
        Member::factory()->count(3)->pending()->create(['member_category_id' => $adult->id]);

        $activities = Activity::where('allows_enrollment', true)->where(fn ($q) => $q->where('min_age', '<=', 18)->orWhereNull('min_age'))->get();
        foreach ($members as $member) {
            foreach ($activities->random(min(rand(0, 2), $activities->count())) as $activity) {
                rescue(fn () => $enrollments->enroll($member, $activity), report: false);
            }
        }

        for ($i = 4; $i >= 0; $i--) {
            foreach ($members as $member) {
                $fees->generateForMember($member, today()->subMonthsNoOverflow($i)->startOfMonth());
            }
        }
        $fees->markOverdue();

        foreach ($members as $index => $member) {
            $open = $member->openFees()->orderBy('due_date')->get();
            $toPay = $index % 6 === 0 ? $open->slice(0, max(0, $open->count() - 3)) : $open->filter(fn ($f) => $f->due_date->lt(today()));

            foreach ($toPay as $fee) {
                $payments->register($member, $fee->balance(), fake()->randomElement(PaymentMethod::cases()), [$fee->id],
                    min($fee->due_date->copy()->subDays(rand(0, 5)), today()), receivedBy: $admin);
            }
        }

        if ($facility = Facility::bookable()->first()) {
            foreach ($members->random(6)->values() as $i => $member) {
                rescue(fn () => $reservations->book($facility, $member, today()->addDays($i % 5 + 1), $facility->slots()[$i % count($facility->slots())]['start'], 1, $admin), report: false);
            }
        }

        return $members;
    }

    /** @param  Collection<int, array<string, mixed>>  $shared */
    private function seedGym(int $count, Collection $shared): void
    {
        $category = MemberCategory::firstOrCreate(['name' => 'Socio'], ['monthly_fee' => 0, 'description' => 'Socio del gimnasio']);
        $base = ['member_category_id' => $category->id, 'admission_date' => today()->subMonths(3)];

        Member::factory()->count($count)->withNumber()->create($base);
        foreach ($shared->values() as $i => $person) {
            Member::factory()->create([...$base, ...$person, 'member_number' => str_pad((string) ($count + $i + 1), 5, '0', STR_PAD_LEFT)]);
        }

        $this->call(DemoGymSeeder::class);
    }

    private function seedDemoAccounts(Organization $principal, Organization $atletico, Organization $gym, Member $demoMember): void
    {
        // Socio en las tres entidades con una sola cuenta.
        $user = User::firstOrCreate(['email' => 'socio@demo.com'], ['name' => 'Socio Demo', 'password' => 'Socio1234', 'is_active' => true]);
        $identity = ['first_name' => 'Socio', 'last_name' => 'Demo', 'email' => 'socio@demo.com', 'document_number' => '30000001'];

        Organization::runFor($principal, fn () => $demoMember->update([...$identity, 'user_id' => $user->id]));

        foreach ([$atletico, $gym] as $org) {
            Organization::runFor($org, function (Organization $org) use ($identity, $user) {
                $member = Member::factory()->create([
                    ...$identity,
                    'member_category_id' => MemberCategory::query()->orderBy('sort_order')->value('id'),
                    'member_number' => 'D-0001',
                    'admission_date' => today()->subMonths(2),
                    'birth_date' => today()->subYears(32),
                    'user_id' => $user->id,
                ]);

                if ($org->usesGym() && ($plan = Plan::where('name', 'Full')->first())) {
                    $subscription = app(SubscriptionService::class)->subscribe($member, $plan);
                    $fee = $subscription->fees()->first();
                    app(PaymentService::class)->register($member, $fee->balance(), PaymentMethod::Cash, [$fee->id]);
                }
            });
        }

        // Personal con roles distintos por entidad.
        $treasurer = User::firstOrCreate(['email' => 'tesoreria@demo.com'], ['name' => 'Tesorería Demo', 'password' => 'Demo1234', 'is_active' => true]);
        $reception = User::firstOrCreate(['email' => 'recepcion@demo.com'], ['name' => 'Recepción Demo', 'password' => 'Demo1234', 'is_active' => true]);

        Organization::runFor($principal, fn () => $treasurer->assignRole('tesorero'));
        Organization::runFor($atletico, fn () => $treasurer->assignRole('tesorero'));
        Organization::runFor($gym, fn () => $reception->assignRole('recepcion'));
    }
}
