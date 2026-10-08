<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\LessonStatus;
use App\Enums\ReceiptStatus;
use App\Livewire\Admin\Lessons\Cash;
use App\Livewire\Admin\Lessons\Collect;
use App\Livewire\Admin\Lessons\Today;
use App\Livewire\Admin\Receipts\Index as Receipts;
use App\Livewire\Admin\Settlements\Index as Settlements;
use App\Livewire\Portal\Fees;
use App\Livewire\Portal\Lessons;
use App\Models\Activity;
use App\Models\CashSettlement;
use App\Models\Fee;
use App\Models\Lesson;
use App\Models\PaymentReceipt;
use App\Models\Setting;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\LevelLessonService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MobileScreensTest extends TestCase
{
    private User $teacher;

    private Activity $level;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(today()->setTime(10, 0));
        Setting::set('activities.label_singular', 'Nivel');
        Setting::set('activities.label_plural', 'Niveles');
        $this->teacher = $this->staff('profesor');
        $this->level = Activity::factory()->create(['name' => 'Juvenil B', 'monthly_fee' => 0, 'instructor_id' => $this->teacher->id]);
        $this->level->schedules()->create(['day_of_week' => today()->dayOfWeekIso, 'start_time' => '23:00', 'end_time' => '23:59']);
    }

    private function student(): User
    {
        $user = $this->memberUser();
        app(EnrollmentService::class)->enroll($user->currentMember(), $this->level);
        app(LevelLessonService::class)->generate($this->level, today(), today());

        return $user;
    }

    public function test_student_notifies_absence_from_portal_and_teacher_sees_it_today(): void
    {
        $user = $this->student();
        $lesson = Lesson::sole();

        $this->actingAs($user);
        Livewire::test(Lessons::class)
            ->assertSee('Juvenil B')
            ->call('askAbsence', $lesson->id)
            ->set('reason', 'Turno médico')
            ->call('confirmAbsence')
            ->assertSet('noticeLessonId', null);

        $this->assertSame(AttendanceStatus::Notified, $lesson->fresh()->attendanceOf($user->currentMember()));

        $this->actingAs($this->teacher);
        Livewire::test(Today::class)
            ->assertSee('Juvenil B')
            ->call('toggle', $lesson->id)
            ->assertSee('Turno médico');
    }

    public function test_teacher_suspends_class_from_today(): void
    {
        $this->student();
        $lesson = Lesson::sole();

        $this->actingAs($this->teacher);
        Livewire::test(Today::class)
            ->call('askSuspend', $lesson->id)
            ->set('suspendReason', 'Estoy enfermo')
            ->call('confirmSuspend')
            ->assertSet('showSuspend', false);

        $this->assertSame(LessonStatus::Cancelled, $lesson->fresh()->status);
    }

    public function test_student_reports_transfer_with_receipt_and_treasury_approves(): void
    {
        Storage::fake('public');
        $user = $this->student();
        $fee = Fee::factory()->create(['member_id' => $user->currentMember()->id, 'amount' => 18000]);

        $this->actingAs($user);
        Livewire::test(Fees::class)
            ->call('openReport', $fee->id)
            ->assertSet('amount', '18000.00')
            ->set('file', UploadedFile::fake()->image('comprobante.jpg'))
            ->call('submitReport')
            ->assertHasNoErrors()
            ->assertSet('tab', 'comprobantes');

        $receipt = PaymentReceipt::sole();
        Storage::disk('public')->assertExists($receipt->file_path);

        $this->actingAs($this->staff('tesorero'));
        Livewire::test(Receipts::class)
            ->assertSee($user->currentMember()->fullName())
            ->call('view', $receipt->id)
            ->call('approve');

        $this->assertSame(ReceiptStatus::Approved, $receipt->fresh()->status);
        $this->assertFalse($fee->fresh()->isOpen());
    }

    public function test_teacher_collects_cash_settles_and_coordination_confirms(): void
    {
        $user = $this->student();
        $member = $user->currentMember();
        Fee::factory()->create(['member_id' => $member->id, 'amount' => 12000, 'concept' => 'Cuota Juvenil B']);

        $this->actingAs($this->teacher);
        Livewire::test(Collect::class)
            ->assertSee($member->sortableName())
            ->call('select', $member->id)
            ->assertSee('Cuota Juvenil B')
            ->call('collect')
            ->assertSet('memberId', null);

        Livewire::test(Cash::class)->assertSee('12.000')->call('settle');
        $settlement = CashSettlement::sole();

        $this->actingAs($this->staff('coordinacion'));
        Livewire::test(Settlements::class)->assertSee($this->teacher->name)->call('confirm', $settlement->id);

        $this->assertNotNull($settlement->fresh()->confirmed_at);
    }

    public function test_teacher_home_is_today_and_labels_are_configurable(): void
    {
        $this->assertSame(route('admin.lessons.today'), $this->teacher->homeRoute());

        $this->actingAs($this->staff('administrador'))
            ->get(route('admin.activities.index'))
            ->assertOk()
            ->assertSee('Niveles')
            ->assertSee('Agregar nivel');
    }

    public function test_push_subscription_endpoint(): void
    {
        $this->actingAs($this->teacher)->postJson(route('push.subscribe'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth' => 'tBHItJI5svbpez7KI4CCXg'],
        ])->assertOk();

        $this->assertSame(1, $this->teacher->pushSubscriptions()->count());

        $this->postJson(route('push.unsubscribe'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])->assertOk();
        $this->assertSame(0, $this->teacher->pushSubscriptions()->count());
    }

    public function test_mobile_pages_render(): void
    {
        $user = $this->student();
        $this->actingAs($user)->get(route('portal.dashboard'))->assertOk()->assertSee('Juvenil B');
        $this->get(route('portal.fees'))->assertOk();
        $this->get(route('portal.lessons'))->assertOk();

        $this->actingAs($this->teacher);
        foreach (['admin.lessons.today', 'admin.lessons.collect', 'admin.lessons.cash', 'admin.lessons'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.receipts'))->assertForbidden();
    }
}
