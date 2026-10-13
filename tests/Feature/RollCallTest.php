<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\LessonStatus;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Admin\Access\Check;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\Setting;
use App\Services\EnrollmentService;
use App\Services\LessonService;
use App\Services\LevelLessonService;
use Livewire\Livewire;
use Tests\TestCase;

/** Control de acceso › Asistencia por nivel (lista tipo colegio). */
class RollCallTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('club.charge_activity_on_enroll', false);
    }

    private function levelWithStudents(int $count = 3): array
    {
        $teacher = $this->staff('profesor');
        $level = Activity::factory()->create(['instructor_id' => $teacher->id]);
        $level->instructors()->sync([$teacher->id]);
        $students = collect(range(1, $count))->map(function () use ($level) {
            $member = $this->activeMember();
            app(EnrollmentService::class)->enroll($member, $level);

            return $member;
        });

        return [$teacher, $level, $students];
    }

    public function test_teacher_opens_access_screen_in_attendance_mode_with_everyone_present(): void
    {
        [$teacher, $level, $students] = $this->levelWithStudents();
        $this->actingAs($teacher);

        $this->get(route('admin.access'))->assertOk();

        Livewire::test(Check::class)
            ->assertSet('mode', 'asistencia')
            ->assertSet('activityId', $level->id)
            ->assertSet('absent', [])
            ->assertSee($students[0]->sortableName())
            ->assertSee('Presentes');
    }

    public function test_unchecking_absent_students_saves_attendance_even_without_a_scheduled_class(): void
    {
        [$teacher, $level, $students] = $this->levelWithStudents();
        $this->actingAs($teacher);

        Livewire::test(Check::class)
            ->call('toggleAbsent', $students[1]->id)
            ->call('saveAttendance');

        $lesson = Lesson::where('activity_id', $level->id)->whereDate('date', today())->sole();
        $this->assertSame(LessonStatus::Given, $lesson->status);
        $this->assertSame(AttendanceStatus::Present->value, $lesson->students()->whereKey($students[0]->id)->first()->pivot->attendance);
        $this->assertSame(AttendanceStatus::Absent->value, $lesson->students()->whereKey($students[1]->id)->first()->pivot->attendance);

        // Se puede corregir: al volver a entrar se ve lo guardado y se reemplaza.
        Livewire::test(Check::class)
            ->assertSet('absent', [$students[1]->id])
            ->call('allPresent')
            ->call('saveAttendance');

        $this->assertSame(1, Lesson::where('activity_id', $level->id)->count());
        $this->assertSame(AttendanceStatus::Present->value, $lesson->students()->whereKey($students[1]->id)->first()->pivot->attendance);
    }

    public function test_uses_the_scheduled_class_and_respects_absence_notice(): void
    {
        $this->travelTo(today()->setTime(10, 0));
        [$teacher, $level, $students] = $this->levelWithStudents(2);
        $level->schedules()->create(['day_of_week' => today()->dayOfWeekIso, 'start_time' => '23:00', 'end_time' => '23:30']);
        app(LevelLessonService::class)->generate($level, today(), today());
        $lesson = Lesson::where('activity_id', $level->id)->sole();
        app(LevelLessonService::class)->notifyAbsence($lesson, $students[0], 'Enfermo');

        $this->actingAs($teacher);
        Livewire::test(Check::class)
            ->assertSet('absent', [$students[0]->id])
            ->assertSee('Enfermo')
            ->call('saveAttendance');

        $this->assertSame(1, Lesson::where('activity_id', $level->id)->count());
        $this->assertSame(AttendanceStatus::Notified->value, $lesson->students()->whereKey($students[0]->id)->first()->pivot->attendance);
    }

    public function test_past_attendance_can_be_disabled_in_settings(): void
    {
        [$teacher, $level, $students] = $this->levelWithStudents(1);
        $yesterday = today()->subDay()->toDateString();
        $this->actingAs($teacher);

        // Permitido (valor por defecto): se toma la asistencia de ayer.
        Livewire::test(Check::class)->set('date', $yesterday)->call('saveAttendance');
        $lesson = Lesson::where('activity_id', $level->id)->whereDate('date', $yesterday)->sole();
        $this->assertSame(LessonStatus::Given, $lesson->status);

        // Desactivado: no se puede elegir otro día ni corregir lo de ayer.
        Setting::set('lessons.past_attendance_allowed', false);
        Livewire::test(Check::class)->set('date', $yesterday)->assertSet('date', today()->toDateString());

        $this->expectException(BusinessRuleException::class);
        app(LessonService::class)->reopen($lesson);
    }

    public function test_teacher_only_sees_own_levels_and_reception_keeps_access_mode(): void
    {
        [$teacher] = $this->levelWithStudents(1);
        $other = Activity::factory()->create(['name' => 'Nivel ajeno']);

        $this->actingAs($teacher);
        Livewire::test(Check::class)->assertDontSee('Nivel ajeno')
            ->set('activityId', $other->id)
            ->assertSet('absent', []);

        $this->actingAs($this->staff('recepcion'));
        Livewire::test(Check::class)->assertSet('mode', 'ingreso')->assertDontSee('Asistencia por');

        $this->actingAs($this->staff('tesorero'));
        $this->get(route('admin.access'))->assertForbidden();
    }
}
