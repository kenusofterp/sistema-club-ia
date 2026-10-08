<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\LessonStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Activity;
use App\Models\Fee;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\AbsenceNoticeNotification;
use App\Notifications\LessonsSuspendedNotification;
use App\Services\EnrollmentService;
use App\Services\FeeService;
use App\Services\LessonService;
use App\Services\LevelLessonService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LevelLessonsTest extends TestCase
{
    private LevelLessonService $levels;

    private User $teacher;

    private User $assistant;

    private Activity $juvenilA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->levels = app(LevelLessonService::class);
        $this->teacher = $this->staff('profesor');
        $this->assistant = $this->staff('profesor');

        $this->juvenilA = Activity::factory()->create(['name' => 'Juvenil A', 'monthly_fee' => 20000, 'instructor_id' => $this->teacher->id]);
        $this->juvenilA->instructors()->sync([$this->teacher->id, $this->assistant->id]);
        // Lunes y miércoles a las 18.
        $this->juvenilA->schedules()->createMany([
            ['day_of_week' => 1, 'start_time' => '18:00', 'end_time' => '19:30'],
            ['day_of_week' => 3, 'start_time' => '18:00', 'end_time' => '19:30'],
        ]);
    }

    private function nextMonday(): Carbon
    {
        return today()->next(Carbon::MONDAY);
    }

    public function test_generates_lessons_idempotently_with_enrolled_students(): void
    {
        $student = $this->activeMember();
        app(EnrollmentService::class)->enroll($student, $this->juvenilA);

        $created = $this->levels->generate($this->juvenilA, $this->nextMonday(), $this->nextMonday()->addDays(13));
        $again = $this->levels->generate($this->juvenilA, $this->nextMonday(), $this->nextMonday()->addDays(13));

        $this->assertSame(4, $created);
        $this->assertSame(0, $again);
        $this->assertSame(4, $student->lessons()->count());
        $this->assertSame($this->teacher->id, Lesson::first()->instructor_id);
    }

    public function test_enrollment_changes_sync_future_lessons(): void
    {
        $this->levels->generate($this->juvenilA, $this->nextMonday(), $this->nextMonday()->addDays(6));
        $student = $this->activeMember();

        $enrollment = app(EnrollmentService::class)->enroll($student, $this->juvenilA);
        $this->assertSame(2, $student->lessons()->count());

        app(EnrollmentService::class)->unenroll($enrollment);
        $this->assertSame(0, $student->lessons()->count());
    }

    public function test_level_lesson_does_not_charge_per_class_and_keeps_absence_notice(): void
    {
        $present = $this->activeMember();
        $noticed = $this->activeMember();
        foreach ([$present, $noticed] as $m) {
            app(EnrollmentService::class)->enroll($m, $this->juvenilA);
        }
        $this->levels->generate($this->juvenilA, $this->nextMonday(), $this->nextMonday());
        $lesson = Lesson::sole();

        $this->levels->notifyAbsence($lesson, $noticed, 'Tengo examen');
        Notification::assertSentTo([$this->teacher, $this->assistant], AbsenceNoticeNotification::class);
        $this->assertSame(AttendanceStatus::Notified, $lesson->fresh()->attendanceOf($noticed));

        $this->travelTo($this->nextMonday()->setTime(20, 0));
        app(LessonService::class)->markGiven($lesson, []);

        $lesson->refresh();
        $this->assertSame(AttendanceStatus::Present, $lesson->attendanceOf($present));
        $this->assertSame(AttendanceStatus::Notified, $lesson->attendanceOf($noticed));
        $this->assertSame(0, Fee::where('type', 'clase')->count());
    }

    public function test_absence_notice_closes_when_class_starts(): void
    {
        $student = $this->activeMember();
        app(EnrollmentService::class)->enroll($student, $this->juvenilA);
        $this->levels->generate($this->juvenilA, $this->nextMonday(), $this->nextMonday());

        $this->travelTo($this->nextMonday()->setTime(18, 5));
        $this->expectException(BusinessRuleException::class);
        $this->levels->notifyAbsence(Lesson::sole(), $student);
    }

    public function test_coordination_suspends_all_levels_and_students_are_notified(): void
    {
        $juvenilB = Activity::factory()->create(['name' => 'Juvenil B', 'instructor_id' => $this->assistant->id]);
        $juvenilB->schedules()->create(['day_of_week' => 1, 'start_time' => '19:30', 'end_time' => '21:00']);
        $a = $this->activeMember();
        $b = $this->activeMember();
        app(EnrollmentService::class)->enroll($a, $this->juvenilA);
        app(EnrollmentService::class)->enroll($b, $juvenilB);

        $this->actingAs($coordinator = $this->staff('coordinacion'));
        $count = $this->levels->suspend($this->nextMonday(), null, 'Lluvia', $coordinator);

        $this->assertSame(2, $count);
        $this->assertSame(2, Lesson::where('status', LessonStatus::Cancelled)->count());
        Notification::assertSentTo($a, LessonsSuspendedNotification::class, fn ($n) => $n->classes === ['Juvenil A 18:00'] && $n->reason === 'Lluvia');
        Notification::assertSentTo($b, LessonsSuspendedNotification::class);
    }

    public function test_teacher_can_only_suspend_own_levels(): void
    {
        $other = Activity::factory()->create(['name' => 'Mayores', 'instructor_id' => $this->staff('profesor')->id]);
        $other->schedules()->create(['day_of_week' => 1, 'start_time' => '10:00', 'end_time' => '11:00']);

        $this->actingAs($this->teacher);
        $count = $this->levels->suspend($this->nextMonday(), null, null, $this->teacher);

        $this->assertSame(1, $count);
        $this->assertSame(LessonStatus::Scheduled, Lesson::where('activity_id', $other->id)->sole()->status);
    }

    public function test_schedule_change_removes_stale_future_lessons(): void
    {
        $this->levels->generate($this->juvenilA, $this->nextMonday(), $this->nextMonday()->addDays(6));
        $this->juvenilA->schedules()->where('day_of_week', 3)->delete();

        $this->levels->syncSchedules($this->juvenilA);

        $this->assertSame(0, Lesson::where('activity_id', $this->juvenilA->id)->get()->filter(fn ($l) => $l->date->dayOfWeekIso === 3)->count());
    }

    public function test_scholarship_amount_replaces_level_fee(): void
    {
        $student = $this->activeMember();
        $enrollment = app(EnrollmentService::class)->enroll($student, $this->juvenilA);
        Fee::query()->delete();
        $enrollment->update(['fee_amount' => 5000]);

        app(FeeService::class)->generateActivityFee($student, $this->juvenilA, today()->addMonth());

        $this->assertEquals(5000, Fee::where('member_id', $student->id)->sole()->amount);
    }
}
