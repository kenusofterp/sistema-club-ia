<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\SettlementStatus;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Admin\Lessons\Cash;
use App\Livewire\Admin\Lessons\Today;
use App\Models\Activity;
use App\Models\Enrollment;
use App\Models\Fee;
use App\Models\Lesson;
use App\Models\Setting;
use App\Services\CashCollectionService;
use App\Services\EnrollmentService;
use App\Services\LevelLessonService;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceAndSelfSettlementTest extends TestCase
{
    private function levelToday(): array
    {
        $teacher = $this->staff('profesor');
        $level = Activity::factory()->create(['instructor_id' => $teacher->id, 'monthly_fee' => 0]);
        $level->instructors()->sync([$teacher->id]);
        $level->schedules()->create(['day_of_week' => today()->dayOfWeekIso, 'start_time' => '00:00', 'end_time' => '00:30']);

        return [$teacher, $level];
    }

    public function test_today_screen_generates_the_class_and_lists_every_enrolled_student_as_present(): void
    {
        [$teacher, $level] = $this->levelToday();
        $early = $this->activeMember();
        app(EnrollmentService::class)->enroll($early, $level);
        app(LevelLessonService::class)->generate($level, today(), today());

        // Inscripta "a mano" después de generada la clase (sin pasar por la sincronización).
        $late = $this->activeMember();
        Enrollment::create(['member_id' => $late->id, 'activity_id' => $level->id, 'status' => 'activa', 'start_date' => today()]);

        $this->actingAs($teacher);
        $lesson = Lesson::where('activity_id', $level->id)->sole();

        Livewire::test(Today::class)
            ->assertSet("attendance.{$lesson->id}.{$early->id}", 'presente')
            ->assertSet("attendance.{$lesson->id}.{$late->id}", 'presente')
            ->call('toggle', $lesson->id)
            ->assertSee('Presentes')
            ->call('setAttendance', $lesson->id, $late->id, 'ausente')
            ->call('markGiven', $lesson->id);

        $this->assertSame(AttendanceStatus::Present->value, $lesson->students()->whereKey($early->id)->first()->pivot->attendance);
        $this->assertSame(AttendanceStatus::Absent->value, $lesson->students()->whereKey($late->id)->first()->pivot->attendance);
    }

    public function test_set_all_marks_everyone(): void
    {
        [$teacher, $level] = $this->levelToday();
        $a = $this->activeMember();
        $b = $this->activeMember();
        app(EnrollmentService::class)->enroll($a, $level);
        app(EnrollmentService::class)->enroll($b, $level);

        $this->actingAs($teacher);
        $component = Livewire::test(Today::class);
        $lesson = Lesson::where('activity_id', $level->id)->sole();

        $component->call('setAll', $lesson->id, false)
            ->assertSet("attendance.{$lesson->id}.{$a->id}", 'ausente')
            ->assertSet("attendance.{$lesson->id}.{$b->id}", 'ausente')
            ->call('setAll', $lesson->id, true)
            ->assertSet("attendance.{$lesson->id}.{$b->id}", 'presente');
    }

    private function settlementOf($teacher)
    {
        $level = Activity::factory()->create(['instructor_id' => $teacher->id, 'monthly_fee' => 0]);
        $student = $this->activeMember();
        app(EnrollmentService::class)->enroll($student, $level);
        $fee = Fee::factory()->create(['member_id' => $student->id, 'amount' => 5000]);
        $service = app(CashCollectionService::class);
        $service->collect($teacher, $student, [$fee->id], '5000');

        return $service->settle($teacher);
    }

    public function test_teacher_cannot_confirm_own_settlement_by_default(): void
    {
        $teacher = $this->staff('profesor');
        $settlement = $this->settlementOf($teacher);

        $this->expectException(BusinessRuleException::class);
        app(CashCollectionService::class)->confirm($settlement, $teacher);
    }

    public function test_teacher_confirms_own_settlement_when_enabled(): void
    {
        Setting::set('payments.self_settlement_allowed', true);
        $teacher = $this->staff('profesor');
        $settlement = $this->settlementOf($teacher);

        $this->actingAs($teacher);
        Livewire::test(Cash::class)
            ->assertSee('Confirmar')
            ->call('confirmOwn', $settlement->id);

        $this->assertSame(SettlementStatus::Confirmed, $settlement->fresh()->status);
        $this->assertSame($teacher->id, $settlement->fresh()->confirmed_by);
    }
}
