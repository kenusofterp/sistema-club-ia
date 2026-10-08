<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Enums\LessonStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Facility;
use App\Models\Fee;
use App\Models\Lesson;
use App\Models\MemberCategory;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccessService;
use App\Services\LessonService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LessonServiceTest extends TestCase
{
    private LessonService $service;

    private User $coach;

    private Facility $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LessonService::class);
        $this->coach = $this->staff('profesor');
        $this->court = Facility::factory()->create(['name' => 'Cancha 1']);
        $this->coach->facilities()->attach($this->court);
        Setting::set('lessons.single_price', 5000);
        Setting::set('gym.activate_on_payment', false);
    }

    private function lesson(array $members = [], ?Carbon $date = null, string $start = '10:00', string $end = '11:00', ?string $price = null): Lesson
    {
        return $this->service->schedule($this->coach, $this->court, $date ?? today(), $start, $end, collect($members)->pluck('id')->all(), $price);
    }

    private function pack(int $classes = 4): Plan
    {
        return Plan::factory()->create(['instructor_id' => $this->coach->id, 'price' => 20000, 'visit_limit' => $classes, 'visit_period' => 'plan']);
    }

    public function test_instructor_cannot_have_overlapping_lessons_even_in_other_organization(): void
    {
        $this->lesson();

        $other = $this->makeOrganization('Club Norte', 'norte');
        $otherCourt = Organization::runFor($other, fn () => Facility::factory()->create(['name' => 'Cancha Norte']));
        $this->coach->facilities()->attach($otherCourt);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Club de Prueba');
        $this->service->schedule($this->coach, $otherCourt, today(), '10:30', '11:30');
    }

    public function test_lesson_belongs_to_the_organization_of_its_facility(): void
    {
        $other = $this->makeOrganization('Club Norte', 'norte');
        $otherCourt = Organization::runFor($other, fn () => Facility::factory()->create());
        $this->coach->facilities()->attach($otherCourt);

        $lesson = $this->service->schedule($this->coach, $otherCourt, today(), '18:00', '19:00');

        $this->assertSame($other->id, $lesson->organization_id);
        $this->assertNull(Lesson::find($lesson->id)); // no aparece en la entidad actual
    }

    public function test_facility_must_be_assigned_to_instructor(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->service->schedule($this->coach, Facility::factory()->create(), today(), '10:00', '11:00');
    }

    public function test_weekly_series_creates_lessons_with_students_and_rejects_conflicts(): void
    {
        $student = $this->activeMember();
        $monday = today()->next(Carbon::MONDAY);

        $series = $this->service->scheduleSeries($this->coach, $this->court, [1, 3], '09:00', '10:00', $monday, $monday->copy()->addWeeks(2)->addDays(2), [$student->id]);

        $this->assertSame(6, $series->lessons()->count()); // 3 lunes + 3 miércoles
        $this->assertSame(6, $student->lessons()->count());

        $this->expectException(BusinessRuleException::class);
        $this->service->scheduleSeries($this->coach, $this->court, [3], '09:30', '10:30', $monday, $monday->copy()->addWeek());
    }

    public function test_cancel_series_from_a_date(): void
    {
        $monday = today()->next(Carbon::MONDAY);
        $series = $this->service->scheduleSeries($this->coach, $this->court, [1], '09:00', '10:00', $monday, $monday->copy()->addWeeks(3));

        $cancelled = $this->service->cancelSeries($series, $monday->copy()->addWeeks(2), 'Vacaciones');

        $this->assertSame(2, $cancelled);
        $this->assertSame(2, $series->lessons()->where('status', LessonStatus::Scheduled)->count());
    }

    public function test_attendance_consumes_one_class_per_lesson_from_instructor_pack(): void
    {
        $student = $this->activeMember();
        $subscription = app(SubscriptionService::class)->subscribe($student, $this->pack(4));

        $this->service->markGiven($this->lesson([$student], start: '08:00', end: '09:00'), [$student->id => 'presente']);
        $this->service->markGiven($this->lesson([$student], start: '18:00', end: '19:00'), [$student->id => 'presente']);

        $this->assertSame(2, $subscription->fresh()->visitsUsed());
        $this->assertSame(2, $subscription->fresh()->visitsRemaining());
        $this->assertSame(0, Fee::where('type', FeeType::Lesson)->count());
    }

    public function test_student_without_pack_pays_single_class_to_the_instructor(): void
    {
        $student = $this->activeMember();
        $lesson = $this->lesson([$student]);

        $this->service->markGiven($lesson, [$student->id => 'presente']);

        $fee = Fee::where('type', FeeType::Lesson)->sole();
        $this->assertEquals(5000, $fee->amount);
        $this->assertSame($this->coach->id, $fee->instructor_id);
        $this->assertSame($lesson->id, $fee->lesson_id);
        $this->assertSame(LessonStatus::Given, $lesson->fresh()->status);
    }

    public function test_lesson_price_and_instructor_price_override_default(): void
    {
        $a = $this->activeMember();
        $b = $this->activeMember();

        $this->service->markGiven($this->lesson([$a], start: '08:00', end: '09:00', price: '7500'), []);
        $this->coach->setPreference('lessons.single_price', '6000');
        $this->service->markGiven($this->lesson([$b], start: '09:00', end: '10:00'), []);

        $this->assertEquals(7500, Fee::where('member_id', $a->id)->sole()->amount);
        $this->assertEquals(6000, Fee::where('member_id', $b->id)->sole()->amount);
    }

    public function test_group_lesson_mixes_pack_single_charge_and_absence(): void
    {
        [$withPack, $withoutPack, $absent] = [$this->activeMember(), $this->activeMember(), $this->activeMember()];
        $subscription = app(SubscriptionService::class)->subscribe($withPack, $this->pack(1));

        // El pack de una sola clase ya está usado: la segunda clase se cobra suelta.
        $this->service->markGiven($this->lesson([$withPack], start: '07:00', end: '08:00'), []);
        $lesson = $this->lesson([$withPack, $withoutPack, $absent]);
        $this->service->markGiven($lesson, [$absent->id => 'ausente']);

        $this->assertSame(1, $subscription->fresh()->visitsUsed());
        $this->assertSame(2, Fee::where('lesson_id', $lesson->id)->count());
        $this->assertSame(AttendanceStatus::Absent, $lesson->fresh()->attendanceOf($absent));
        $this->assertSame(0, Fee::where('member_id', $absent->id)->count());
    }

    public function test_reopen_cancels_unpaid_single_charges(): void
    {
        $student = $this->activeMember();
        $lesson = $this->service->markGiven($this->lesson([$student]), []);

        $this->service->reopen($lesson);

        $this->assertSame(FeeStatus::Cancelled, Fee::where('lesson_id', $lesson->id)->sole()->status);
        $this->assertSame(LessonStatus::Scheduled, $lesson->fresh()->status);
    }

    public function test_student_from_other_organization_joins_without_admission_fee(): void
    {
        $other = $this->makeOrganization('Club Norte', 'norte');
        $foreign = Organization::runFor($other, fn () => $this->activeMember(['document_number' => '33444555']));
        MemberCategory::factory()->create(['admission_fee' => 50000]);

        $lesson = $this->lesson([$foreign]);

        $local = $lesson->students()->sole();
        $this->assertSame($this->organization->id, $local->organization_id);
        $this->assertSame($foreign->person_id, $local->person_id);
        $this->assertSame(0, Fee::where('member_id', $local->id)->count());
    }

    public function test_payments_are_separated_by_instructor(): void
    {
        $student = $this->activeMember();
        $this->service->markGiven($this->lesson([$student]), []);
        $lessonFee = Fee::where('member_id', $student->id)->sole();
        $clubFee = Fee::factory()->create(['member_id' => $student->id, 'amount' => 1000]);

        try {
            app(PaymentService::class)->register($student, '6000', PaymentMethod::Cash, [$lessonFee->id, $clubFee->id]);
            $this->fail('Debió rechazar el pago mezclado.');
        } catch (BusinessRuleException) {
        }

        $payment = app(PaymentService::class)->register($student, '5000', PaymentMethod::Cash, [$lessonFee->id]);
        $this->assertSame($this->coach->id, $payment->instructor_id);
    }

    public function test_instructor_debt_and_packs_do_not_affect_club_access(): void
    {
        $student = $this->activeMember();
        Fee::factory()->overdue()->count(3)->create(['member_id' => $student->id, 'instructor_id' => $this->coach->id]);

        $this->assertSame(0, $student->overdueFeesCount());
        $this->assertSame('permitido', app(AccessService::class)->evaluate($student)['result']->value);
    }
}
