<?php

namespace Tests\Feature;

use App\Enums\FeeStatus;
use App\Enums\LessonStatus;
use App\Livewire\Admin\Lessons\Account;
use App\Livewire\Admin\Lessons\Calendar;
use App\Livewire\Admin\Lessons\Packs;
use App\Models\Facility;
use App\Models\Fee;
use App\Models\Lesson;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\LessonService;
use Livewire\Livewire;
use Tests\TestCase;

class LessonScreensTest extends TestCase
{
    private User $coach;

    private Facility $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coach = $this->staff('profesor');
        $this->court = Facility::factory()->create(['name' => 'Cancha Central']);
        $this->coach->facilities()->attach($this->court);
        Setting::set('lessons.single_price', 5000);
    }

    public function test_coach_schedules_lesson_and_marks_it_given_from_calendar(): void
    {
        $student = $this->activeMember(['first_name' => 'Valentina']);
        $this->actingAs($this->coach);

        Livewire::test(Calendar::class)
            ->call('create', today()->toDateString(), '10:00')
            ->assertSet('facilityId', $this->court->id)
            ->set('studentSearch', 'Valentina')
            ->assertSee('Valentina')
            ->call('addStudent', $student->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $lesson = Lesson::sole();
        $this->assertSame('10:00:00', $lesson->start_time);

        Livewire::test(Calendar::class)
            ->call('show', $lesson->id)
            ->call('markGiven');

        $this->assertSame(LessonStatus::Given, $lesson->fresh()->status);
        $this->assertSame(1, Fee::where('lesson_id', $lesson->id)->count());
    }

    public function test_view_and_scope_are_remembered_per_user(): void
    {
        $this->actingAs($this->coach);

        Livewire::test(Calendar::class)
            ->set('view', 'mes')
            ->set('scope', (string) $this->organization->id);

        $this->coach->refresh();
        $this->assertSame('mes', $this->coach->preference('agenda.view'));

        Livewire::test(Calendar::class)
            ->assertSet('view', 'mes')
            ->assertSet('scope', (string) $this->organization->id);
    }

    public function test_global_agenda_shows_lessons_from_every_club_of_the_coach(): void
    {
        $other = $this->makeOrganization('Club Norte', 'norte');
        Organization::runFor($other, fn () => $this->coach->assignRole('profesor'));
        $northCourt = Organization::runFor($other, fn () => Facility::factory()->create(['name' => 'Cancha Norte']));
        $this->coach->facilities()->attach($northCourt);

        $service = app(LessonService::class);
        $service->schedule($this->coach, $this->court, today(), '09:00', '10:00');
        $service->schedule($this->coach, $northCourt, today(), '18:00', '19:00');

        $this->actingAs($this->coach);
        Livewire::test(Calendar::class, ['view' => 'dia'])
            ->set('scope', 'todas')
            ->assertSee('Cancha Central')
            ->assertSee('Cancha Norte')
            ->set('scope', (string) $other->id)
            ->assertDontSee('Cancha Central')
            ->assertSee('Cancha Norte');
    }

    public function test_coach_only_sees_own_lessons_but_admin_sees_all(): void
    {
        $otherCoach = $this->staff('profesor');
        $otherCoach->facilities()->attach($this->court);
        app(LessonService::class)->schedule($otherCoach, $this->court, today(), '09:00', '10:00', notes: 'Clase de otro');
        $lesson = Lesson::sole();

        $this->actingAs($this->coach);
        Livewire::test(Calendar::class, ['view' => 'dia'])->assertDontSee('Cancha Central');
        Livewire::test(Calendar::class)->call('show', $lesson->id)->assertNotFound();

        $this->actingAs($this->staff('administrador'));
        Livewire::test(Calendar::class, ['view' => 'dia'])->assertSee('Cancha Central');
    }

    public function test_coach_creates_pack_and_sells_it(): void
    {
        $student = $this->activeMember();
        $this->actingAs($this->coach);

        Livewire::test(Packs::class)
            ->call('create')
            ->set('name', '8 clases por mes')
            ->set('price', '40000')
            ->set('visit_limit', 8)
            ->call('save')
            ->assertHasNoErrors();

        $pack = Plan::sole();
        $this->assertSame($this->coach->id, $pack->instructor_id);
        $this->assertFalse($pack->is_public);

        Livewire::test(Packs::class)
            ->call('sell', $pack->id)
            ->call('selectStudent', $student->id)
            ->call('confirmSale')
            ->assertSet('showSell', false)
            ->assertSee($student->fullName());

        $fee = Fee::where('member_id', $student->id)->sole();
        $this->assertSame($this->coach->id, $fee->instructor_id);
        $this->assertTrue($student->subscriptions()->current()->exists());
    }

    public function test_coach_collects_from_my_account_screen(): void
    {
        $student = $this->activeMember(['first_name' => 'Tomás']);
        $lesson = app(LessonService::class)->schedule($this->coach, $this->court, today(), '09:00', '10:00', [$student->id]);
        app(LessonService::class)->markGiven($lesson, []);
        Fee::factory()->create(['member_id' => $student->id, 'concept' => 'Cuota del club']);

        $this->actingAs($this->coach);
        Livewire::test(Account::class)
            ->assertSee('Tomás')
            ->assertDontSee('Cuota del club')
            ->call('openPayment', $student->id)
            ->assertSet('amount', '5000.00')
            ->call('pay')
            ->assertSet('showPayment', false);

        $this->assertSame($this->coach->id, Payment::sole()->instructor_id);
        $this->assertSame(FeeStatus::Paid, Fee::where('lesson_id', $lesson->id)->sole()->status);
    }

    public function test_portal_shows_my_lessons(): void
    {
        $user = $this->memberUser();
        app(LessonService::class)->schedule($this->coach, $this->court, today()->addDay(), '09:00', '10:00', [$user->currentMember()->id]);

        $this->actingAs($user)->get(route('portal.lessons'))->assertOk()->assertSee('Cancha Central')->assertSee($this->coach->name);
    }

    public function test_agenda_routes_require_permissions(): void
    {
        $this->actingAs($this->staff('tesorero'));
        $this->get(route('admin.lessons'))->assertForbidden();

        $this->actingAs($this->coach);
        $this->get(route('admin.lessons'))->assertOk();
        $this->get(route('admin.lessons.packs'))->assertOk();
        $this->get(route('admin.lessons.account'))->assertOk();
    }
}
