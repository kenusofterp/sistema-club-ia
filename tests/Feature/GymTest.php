<?php

namespace Tests\Feature;

use App\Enums\AccessResult;
use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Enums\PaymentMethod;
use App\Enums\PlanAccessType;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Admin\Gym\Plans as PlansAdmin;
use App\Livewire\Portal\Plans as PortalPlans;
use App\Models\Activity;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\AccessService;
use App\Services\EnrollmentService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class GymTest extends TestCase
{
    private SubscriptionService $subscriptions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptions = app(SubscriptionService::class);
        $this->setType('gimnasio');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Cambia el tipo de la entidad actual (club, gimnasio o mixto). */
    private function setType(string $type): void
    {
        $this->organization->update(['type' => $type]);
        Organization::setCurrent($this->organization);
    }

    private function pay(Member $member): void
    {
        $fees = $member->openFees()->get();
        app(PaymentService::class)->register($member, $fees->reduce(fn ($c, $f) => bcadd($c, $f->balance(), 2), '0'), PaymentMethod::Cash, $fees->pluck('id')->all());
    }

    private function activeSubscription(Member $member, Plan $plan): Subscription
    {
        $subscription = $this->subscriptions->subscribe($member, $plan);
        $this->pay($member);

        return $subscription->fresh();
    }

    // ---- Contratación y pago ----

    public function test_subscription_is_pending_until_paid_and_creates_charge(): void
    {
        $member = $this->activeMember();
        $plan = Plan::factory()->create(['price' => 25000]);

        $subscription = $this->subscriptions->subscribe($member, $plan);

        $this->assertSame(SubscriptionStatus::Pending, $subscription->status);
        $this->assertSame(today()->addMonthNoOverflow()->subDay()->toDateString(), $subscription->end_date->toDateString());
        $this->assertDatabaseHas('fees', ['subscription_id' => $subscription->id, 'type' => FeeType::Plan->value, 'amount' => 25000]);

        $this->pay($member);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
    }

    public function test_cancelling_the_payment_returns_plan_to_pending(): void
    {
        $member = $this->activeMember();
        $subscription = $this->subscriptions->subscribe($member, Plan::factory()->create());
        $this->pay($member);

        app(PaymentService::class)->cancel($member->payments()->first(), 'Error');

        $this->assertSame(SubscriptionStatus::Pending, $subscription->fresh()->status);
    }

    public function test_plan_can_activate_immediately_when_configured(): void
    {
        Setting::set('gym.activate_on_payment', false);

        $subscription = $this->subscriptions->subscribe($this->activeMember(), Plan::factory()->create());

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
    }

    public function test_late_payment_of_new_plan_starts_counting_from_activation(): void
    {
        $member = $this->activeMember();
        $plan = Plan::factory()->create(['duration_unit' => 'dias', 'duration_value' => 30]);
        $subscription = $this->subscriptions->subscribe($member, $plan);

        Carbon::setTestNow(now()->addDays(5));
        $this->pay($member);

        $subscription->refresh();
        $this->assertTrue($subscription->start_date->isToday());
        $this->assertSame(today()->addDays(29)->toDateString(), $subscription->end_date->toDateString());
    }

    public function test_same_plan_cannot_overlap(): void
    {
        $member = $this->activeMember();
        $plan = Plan::factory()->create();
        $this->subscriptions->subscribe($member, $plan);

        $this->expectException(BusinessRuleException::class);
        $this->subscriptions->subscribe($member, $plan, today()->addDays(5));
    }

    public function test_cancel_voids_unpaid_charge(): void
    {
        $member = $this->activeMember();
        $subscription = $this->subscriptions->subscribe($member, Plan::factory()->create());

        $this->subscriptions->cancel($subscription, 'Se arrepintió');

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->fresh()->status);
        $this->assertSame(FeeStatus::Cancelled, $subscription->fees()->first()->status);
    }

    // ---- Control de acceso ----

    public function test_gym_mode_requires_a_current_plan(): void
    {
        $member = $this->activeMember();
        $access = app(AccessService::class);

        $this->assertSame(AccessResult::Denied, $access->evaluate($member)['result']);

        $this->activeSubscription($member, Plan::factory()->create());
        $result = $access->evaluate($member);
        $this->assertSame(AccessResult::Granted, $result['result']);
        $this->assertNotNull($result['subscription_id']);
    }

    public function test_mixed_mode_lets_members_in_without_plan_unless_configured(): void
    {
        $this->setType('mixto');
        $member = $this->activeMember();
        $access = app(AccessService::class);

        $this->assertSame(AccessResult::Granted, $access->evaluate($member)['result']);

        Setting::set('gym.access_requires_plan', true);
        $this->assertSame(AccessResult::Denied, $access->evaluate($member)['result']);
    }

    public function test_club_mode_ignores_plans(): void
    {
        $this->setType('club');

        $this->assertSame(AccessResult::Granted, app(AccessService::class)->evaluate($this->activeMember())['result']);
    }

    public function test_time_windows_are_enforced(): void
    {
        $member = $this->activeMember();
        $plan = Plan::factory()->create(['access_windows' => [['days' => [1, 2, 3, 4, 5], 'from' => '06:00', 'to' => '14:00']]]);
        $this->activeSubscription($member, $plan);
        $access = app(AccessService::class);

        $monday = today()->next(Carbon::MONDAY);
        $this->assertSame(AccessResult::Granted, $access->evaluate($member, $monday->copy()->setTime(9, 0))['result']);
        $this->assertSame(AccessResult::Denied, $access->evaluate($member, $monday->copy()->setTime(18, 0))['result']);
        $this->assertSame(AccessResult::Denied, $access->evaluate($member, $monday->copy()->next(Carbon::SATURDAY)->setTime(9, 0))['result']);
    }

    public function test_classes_only_plan_grants_access_around_included_class(): void
    {
        Setting::set('gym.class_checkin_tolerance', 20);
        $member = $this->activeMember();
        $yoga = Activity::factory()->create(['name' => 'Yoga', 'allows_enrollment' => false]);
        $yoga->schedules()->create(['day_of_week' => 2, 'start_time' => '18:00', 'end_time' => '19:00']);
        $plan = Plan::factory()->create(['access_type' => PlanAccessType::Classes, 'duration_value' => 2]);
        $plan->activities()->attach($yoga);
        $this->activeSubscription($member, $plan);
        $access = app(AccessService::class);
        $tuesday = today()->next(Carbon::TUESDAY);

        $this->assertSame(AccessResult::Denied, $access->evaluate($member, $tuesday->copy()->setTime(17, 30))['result']);
        $ok = $access->evaluate($member, $tuesday->copy()->setTime(17, 45));
        $this->assertSame(AccessResult::Granted, $ok['result']);
        $this->assertSame($yoga->id, $ok['activity_id']);
        $this->assertSame(AccessResult::Granted, $access->evaluate($member, $tuesday->copy()->setTime(18, 50))['result']);
        $this->assertSame(AccessResult::Denied, $access->evaluate($member, $tuesday->copy()->setTime(19, 10))['result']);
    }

    public function test_visit_limit_counts_one_visit_per_day(): void
    {
        $member = $this->activeMember();
        $plan = Plan::factory()->create(['visit_limit' => 2, 'visit_period' => 'semana']);
        $this->activeSubscription($member, $plan);
        $access = app(AccessService::class);
        $monday = today()->next(Carbon::MONDAY);

        Carbon::setTestNow($monday->copy()->setTime(9, 0));
        $this->assertSame(AccessResult::Granted, $access->register($member)->result);
        Carbon::setTestNow($monday->copy()->setTime(19, 0)); // reingreso el mismo día: no consume
        $this->assertSame(AccessResult::Granted, $access->register($member)->result);
        Carbon::setTestNow($monday->copy()->addDay()->setTime(9, 0));
        $this->assertSame(AccessResult::Granted, $access->register($member)->result);
        Carbon::setTestNow($monday->copy()->addDays(2)->setTime(9, 0));
        $log = $access->register($member);
        $this->assertSame(AccessResult::Denied, $log->result);
        $this->assertStringContainsString('límite', $log->reason);

        Carbon::setTestNow($monday->copy()->addWeek()->setTime(9, 0)); // nueva semana
        $this->assertSame(AccessResult::Granted, $access->register($member)->result);
    }

    // ---- Procesos automáticos ----

    public function test_daily_process_expires_renews_and_cancels(): void
    {
        $plan = Plan::factory()->create(['duration_unit' => 'dias', 'duration_value' => 10]);
        Setting::set('gym.renewal_days_before', 3);
        Setting::set('gym.pending_expiry_days', 7);

        $renewing = $this->activeSubscription($this->activeMember(), $plan);
        $noRenew = $this->activeSubscription($this->activeMember(), $plan);
        $noRenew->update(['auto_renew' => false]);
        $unpaid = $this->subscriptions->subscribe($this->activeMember(), $plan);

        Carbon::setTestNow(today()->addDays(8));
        $result = $this->subscriptions->processDaily();
        $this->assertSame(1, $result['renewed']);
        $this->assertSame(1, $result['cancelled']);
        $this->assertSame(SubscriptionStatus::Cancelled, $unpaid->fresh()->status);

        $renewal = $renewing->fresh()->renewal;
        $this->assertSame($renewing->end_date->copy()->addDay()->toDateString(), $renewal->start_date->toDateString());
        $this->assertSame(SubscriptionStatus::Pending, $renewal->status);

        Carbon::setTestNow(today()->addDays(3));
        $result = $this->subscriptions->processDaily();
        $this->assertSame(2, $result['expired']);
        $this->assertSame(SubscriptionStatus::Expired, $noRenew->fresh()->status);
        $this->assertSame(0, $result['renewed']);
    }

    public function test_plan_only_activity_rejects_monthly_enrollment(): void
    {
        $activity = Activity::factory()->create(['allows_enrollment' => false]);

        $this->expectException(BusinessRuleException::class);
        app(EnrollmentService::class)->enroll($this->activeMember(), $activity);
    }

    // ---- Interfaces ----

    public function test_admin_creates_plan_with_windows_and_classes(): void
    {
        $this->actingAs($this->staff());
        $yoga = Activity::factory()->create();

        Livewire::test(PlansAdmin::class)
            ->call('create')
            ->set('name', 'Mañanas + Yoga')
            ->set('price', '30000')
            ->set('access_type', 'libre_y_clases')
            ->set('activityIds', [(string) $yoga->id])
            ->call('addWindow')
            ->call('save')
            ->assertHasNoErrors();

        $plan = Plan::where('name', 'Mañanas + Yoga')->first();
        $this->assertSame([1, 2, 3, 4, 5], $plan->access_windows[0]['days']);
        $this->assertTrue($plan->activities->contains($yoga));
    }

    public function test_classes_plan_requires_activities(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(PlansAdmin::class)
            ->call('create')
            ->set('name', 'Solo clases')
            ->set('price', '10000')
            ->set('access_type', 'clases')
            ->call('save')
            ->assertHasErrors('activityIds');
    }

    public function test_member_purchases_plan_from_portal(): void
    {
        $user = $this->memberUser();
        $plan = Plan::factory()->create(['name' => 'Musculación']);

        Livewire::actingAs($user)->test(PortalPlans::class)->call('purchase', $plan->id);

        $this->assertSame(SubscriptionStatus::Pending, $user->member->subscriptions()->first()->status);
        $this->actingAs($user)->get(route('portal.plans'))->assertOk()->assertSee('Musculación')->assertSee('Pendiente de pago');
    }

    public function test_gym_screens_render_and_are_hidden_in_club_mode(): void
    {
        $this->seed(SiteContentSeeder::class);
        Plan::factory()->create(['name' => 'Pase libre premium']);
        $this->actingAs($this->staff());

        $this->get(route('admin.gym.plans'))->assertOk()->assertSee('Pase libre premium');
        $this->get(route('admin.gym.subscriptions'))->assertOk();
        $this->get(route('admin.dashboard'))->assertSee('Planes vigentes');
        $this->get('/')->assertSee('Pase libre premium');
        $this->getJson('/api/v1/plans')->assertOk()->assertJsonPath('data.0.name', 'Pase libre premium');

        $this->setType('club');
        $this->get(route('admin.gym.plans'))->assertNotFound();
        $this->get(route('admin.dashboard'))->assertDontSee('Planes de socios');
        $this->get('/')->assertDontSee('Pase libre premium');
    }
}
