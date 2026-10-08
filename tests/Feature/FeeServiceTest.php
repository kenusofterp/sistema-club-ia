<?php

namespace Tests\Feature;

use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Enums\MemberStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Activity;
use App\Models\Fee;
use App\Models\Setting;
use App\Services\EnrollmentService;
use App\Services\FeeService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FeeServiceTest extends TestCase
{
    private FeeService $fees;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fees = app(FeeService::class);
        Setting::set('club.charge_activity_on_enroll', false);
    }

    public function test_generates_membership_and_activity_fees(): void
    {
        $member = $this->activeMember();
        $activity = Activity::factory()->create(['monthly_fee' => 5000]);
        app(EnrollmentService::class)->enroll($member, $activity);

        $created = $this->fees->generateForMember($member, today());

        $this->assertSame(2, $created);
        $this->assertDatabaseHas('fees', ['member_id' => $member->id, 'type' => FeeType::Membership->value, 'amount' => 10000]);
        $this->assertDatabaseHas('fees', ['member_id' => $member->id, 'type' => FeeType::Activity->value, 'activity_id' => $activity->id, 'amount' => 5000]);
    }

    public function test_generation_is_idempotent(): void
    {
        $member = $this->activeMember();

        $this->fees->generateForMember($member, today());
        $this->assertSame(0, $this->fees->generateForMember($member, today()));
        $this->assertSame(1, Fee::count());
    }

    public function test_due_date_uses_configured_day(): void
    {
        Setting::set('club.fee_due_day', 15);
        $member = $this->activeMember();

        $this->fees->generateForMember($member, today()->startOfMonth());

        $this->assertSame(today()->startOfMonth()->setDay(15)->toDateString(), Fee::first()->due_date->toDateString());
    }

    public function test_inactive_members_and_months_before_admission_are_not_charged(): void
    {
        $suspended = $this->activeMember(['status' => MemberStatus::Suspended]);
        $newMember = $this->activeMember(['admission_date' => today()->addMonth()]);

        $this->assertSame(0, $this->fees->generateForMember($suspended, today()));
        $this->assertSame(0, $this->fees->generateForMember($newMember, today()));
    }

    public function test_artisan_command_generates_for_all_active_members(): void
    {
        $this->activeMember();
        $this->activeMember();
        $this->activeMember(['status' => MemberStatus::Inactive]);

        Artisan::call('club:generar-cuotas', ['--periodo' => today()->format('Y-m'), '--sync' => true]);

        $this->assertSame(2, Fee::count());
        $this->assertDatabaseHas('activity_log', ['log_name' => 'fees', 'event' => 'generated']);
    }

    public function test_mark_overdue_applies_surcharge_once(): void
    {
        Setting::set('club.surcharge_percent', 10);
        $member = $this->activeMember();
        $fee = Fee::factory()->create(['member_id' => $member->id, 'amount' => 1000, 'due_date' => today()->subDay()]);

        $this->assertSame(1, $this->fees->markOverdue());
        $fee->refresh();
        $this->assertSame(FeeStatus::Overdue, $fee->status);
        $this->assertSame('100.00', (string) $fee->surcharge);
        $this->assertSame('1100.00', $fee->balance());

        // Ya vencida: no vuelve a procesarse ni duplica recargo.
        $this->assertSame(0, $this->fees->markOverdue());
        $this->assertSame('100.00', (string) $fee->fresh()->surcharge);
    }

    public function test_fees_not_yet_due_are_not_marked_overdue(): void
    {
        $member = $this->activeMember();
        Fee::factory()->create(['member_id' => $member->id, 'due_date' => today()]);

        $this->assertSame(0, $this->fees->markOverdue());
    }

    public function test_cannot_cancel_fee_with_payments(): void
    {
        $fee = Fee::factory()->create(['paid_amount' => 100, 'status' => FeeStatus::Partial]);

        $this->expectException(BusinessRuleException::class);
        $this->fees->cancel($fee, 'error de carga');
    }

    public function test_cancelled_fee_allows_regeneration_of_period(): void
    {
        $member = $this->activeMember();
        $this->fees->generateForMember($member, today());
        $this->fees->cancel(Fee::first(), 'Bonificada');

        $this->assertSame(1, $this->fees->generateForMember($member, today()));
    }
}
