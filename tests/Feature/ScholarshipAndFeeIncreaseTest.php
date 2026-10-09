<?php

namespace Tests\Feature;

use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Admin\Activities\Index as ActivitiesIndex;
use App\Livewire\Admin\Enrollments\Index as EnrollmentsIndex;
use App\Models\Activity;
use App\Models\Enrollment;
use App\Models\Fee;
use App\Models\MemberCategory;
use App\Models\Setting;
use App\Services\ActivityFeeService;
use App\Services\EnrollmentService;
use App\Services\FeeService;
use Livewire\Livewire;
use Tests\TestCase;

/** Becas en %, aumento masivo de cuotas e inscripción anual por nivel. */
class ScholarshipAndFeeIncreaseTest extends TestCase
{
    private function freeMember()
    {
        // Categoría sin cuota social para aislar la cuota del nivel.
        return $this->activeMember([], MemberCategory::factory()->create(['monthly_fee' => 0, 'admission_fee' => 0]));
    }

    private function activityFee(int $memberId): Fee
    {
        return Fee::where('member_id', $memberId)->where('type', FeeType::Activity)->firstOrFail();
    }

    public function test_scholarship_percent_discounts_only_the_monthly_fee(): void
    {
        $member = $this->freeMember();
        $activity = Activity::factory()->create(['monthly_fee' => 20000, 'enrollment_fee' => 5000]);

        app(EnrollmentService::class)->enroll($member, $activity, scholarshipPercent: '25.00');

        $fee = $this->activityFee($member->id);
        $this->assertEquals('15000.00', $fee->amount);
        $this->assertStringContainsString('beca 25 %', $fee->concept);

        // La inscripción anual no lleva beca.
        $this->assertDatabaseHas('fees', ['member_id' => $member->id, 'type' => FeeType::Registration->value, 'amount' => 5000]);
    }

    public function test_scholarship_can_change_month_to_month_and_combines_with_fixed_fee(): void
    {
        Setting::set('club.charge_activity_on_enroll', false);
        $member = $this->freeMember();
        $activity = Activity::factory()->create(['monthly_fee' => 20000]);
        $enrollment = app(EnrollmentService::class)->enroll($member, $activity, scholarshipPercent: '25');
        $fees = app(FeeService::class);

        $fees->generateForMember($member, today()->startOfMonth());
        app(EnrollmentService::class)->updateFee($enrollment, '10000.00', '50.00');
        $fees->generateForMember($member, today()->startOfMonth()->addMonth());

        $amounts = Fee::where('member_id', $member->id)->orderBy('period')->pluck('amount')->all();
        $this->assertEquals(['15000.00', '5000.00'], $amounts);

        // Beca del 100 %: no se genera cuota.
        app(EnrollmentService::class)->updateFee($enrollment, null, '100');
        $this->assertSame(0, $fees->generateForMember($member, today()->startOfMonth()->addMonths(2)));
    }

    public function test_scholarship_must_be_between_0_and_100(): void
    {
        $enrollment = app(EnrollmentService::class)->enroll($this->freeMember(), Activity::factory()->create());

        $this->expectException(BusinessRuleException::class);
        app(EnrollmentService::class)->updateFee($enrollment, null, '120');
    }

    public function test_enrollments_screen_saves_scholarship(): void
    {
        $this->actingAs($this->staff());
        $enrollment = app(EnrollmentService::class)->enroll($this->freeMember(), Activity::factory()->create());

        Livewire::test(EnrollmentsIndex::class)
            ->call('editFee', $enrollment->id)
            ->set('scholarship', '30')
            ->call('saveFee')
            ->assertHasNoErrors();

        $this->assertEquals('30.00', $enrollment->fresh()->scholarship_percent);
        $this->assertNull($enrollment->fresh()->fee_amount);
    }

    public function test_increase_by_percent_and_amount_updates_levels_and_fixed_fees(): void
    {
        $a = Activity::factory()->create(['monthly_fee' => 10000]);
        $b = Activity::factory()->create(['monthly_fee' => 15000]);
        $untouched = Activity::factory()->create(['monthly_fee' => 9000]);
        $enrollment = app(EnrollmentService::class)->enroll($this->freeMember(), $a, feeAmount: '8000.00');
        $service = app(ActivityFeeService::class);

        $service->increase([$a->id, $b->id], ActivityFeeService::MODE_PERCENT, '20');
        $this->assertEquals('12000.00', $a->fresh()->monthly_fee);
        $this->assertEquals('18000.00', $b->fresh()->monthly_fee);
        $this->assertEquals('9600.00', $enrollment->fresh()->fee_amount);
        $this->assertEquals('9000.00', $untouched->fresh()->monthly_fee);

        $service->increase([$a->id], ActivityFeeService::MODE_AMOUNT, '10000');
        $this->assertEquals('22000.00', $a->fresh()->monthly_fee);
        $this->assertEquals('19600.00', $enrollment->fresh()->fee_amount);
    }

    public function test_increase_does_not_change_already_generated_fees(): void
    {
        $member = $this->freeMember();
        $activity = Activity::factory()->create(['monthly_fee' => 10000]);
        app(EnrollmentService::class)->enroll($member, $activity);

        app(ActivityFeeService::class)->increase([$activity->id], ActivityFeeService::MODE_PERCENT, '50');

        $this->assertEquals('10000.00', $this->activityFee($member->id)->amount);
    }

    public function test_increase_screen_applies_to_selected_levels(): void
    {
        $this->actingAs($this->staff());
        $a = Activity::factory()->create(['monthly_fee' => 10000]);
        $b = Activity::factory()->create(['monthly_fee' => 10000]);

        Livewire::test(ActivitiesIndex::class)
            ->call('openIncrease')
            ->set('increaseMode', 'monto')
            ->set('increaseValue', '5000')
            ->set('increaseIds', [(string) $a->id])
            ->assertSee('15.000')
            ->call('applyIncrease')
            ->assertHasNoErrors();

        $this->assertEquals('15000.00', $a->fresh()->monthly_fee);
        $this->assertEquals('10000.00', $b->fresh()->monthly_fee);
    }

    public function test_registration_fee_is_charged_once_per_year(): void
    {
        $member = $this->freeMember();
        $activity = Activity::factory()->create(['monthly_fee' => 0, 'enrollment_fee' => 7000]);
        app(EnrollmentService::class)->enroll($member, $activity);

        // Reinscripción del mismo año: no duplica.
        $this->assertSame(0, app(EnrollmentService::class)->chargeRegistration($activity, today()->year));
        // Año siguiente: se cobra de nuevo.
        $this->assertSame(1, app(EnrollmentService::class)->chargeRegistration($activity, today()->year + 1));

        $fees = Fee::where('member_id', $member->id)->where('type', FeeType::Registration)->get();
        $this->assertCount(2, $fees);
        $this->assertTrue($fees->every(fn (Fee $f) => $f->due_date->gte(today()) && $f->status === FeeStatus::Pending));
    }

    public function test_no_registration_fee_when_level_has_no_cost(): void
    {
        $member = $this->freeMember();
        app(EnrollmentService::class)->enroll($member, Activity::factory()->create(['enrollment_fee' => 0]));

        $this->assertDatabaseMissing('fees', ['member_id' => $member->id, 'type' => FeeType::Registration->value]);
        $this->assertSame(1, Enrollment::count());
    }
}
