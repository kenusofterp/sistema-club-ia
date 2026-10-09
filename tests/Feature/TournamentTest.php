<?php

namespace Tests\Feature;

use App\Enums\AccessResult;
use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Admin\MemberMessages\Compose;
use App\Livewire\Admin\Tournaments\Form as TournamentForm;
use App\Livewire\Admin\Tournaments\Show as TournamentShow;
use App\Livewire\Portal\Messages as PortalMessages;
use App\Livewire\Portal\Tournaments as PortalTournaments;
use App\Models\Activity;
use App\Models\Fee;
use App\Models\MemberCategory;
use App\Models\Setting;
use App\Models\Tournament;
use App\Models\User;
use App\Notifications\MemberMessageNotification;
use App\Services\AccessService;
use App\Services\CashCollectionService;
use App\Services\EnrollmentService;
use App\Services\FeeService;
use App\Services\MemberMessageService;
use App\Services\TournamentService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TournamentTest extends TestCase
{
    private User $teacher;

    private Activity $levelA;

    private Activity $levelB;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('club.charge_activity_on_enroll', false);
        $this->teacher = $this->staff('profesor');
        $this->levelA = Activity::factory()->create(['name' => 'Juvenil A', 'instructor_id' => $this->teacher->id, 'monthly_fee' => 20000]);
        $this->levelB = Activity::factory()->create(['name' => 'Juvenil B', 'instructor_id' => $this->teacher->id, 'monthly_fee' => 20000]);
    }

    private function student(?Activity $level = null)
    {
        $member = $this->activeMember([], MemberCategory::factory()->create(['monthly_fee' => 0]));
        if ($level) {
            app(EnrollmentService::class)->enroll($member, $level);
        }

        return $member;
    }

    private function tournament(array $overrides = [], ?array $days = null): Tournament
    {
        return app(TournamentService::class)->save(null, [
            'name' => 'Primavera',
            'price' => '15000.00',
            'payment_due_date' => today()->addDays(5)->toDateString(),
            'is_open' => true,
            ...$overrides,
        ], $days ?? [
            ['date' => today()->addDays(10)->toDateString(), 'activity_ids' => [$this->levelA->id]],
            ['date' => today()->addDays(11)->toDateString(), 'activity_ids' => [$this->levelB->id]],
        ], $this->teacher);
    }

    public function test_tournament_has_days_with_levels(): void
    {
        $tournament = $this->tournament();

        $this->assertCount(2, $tournament->days);
        $this->assertEqualsCanonicalizing([$this->levelA->id, $this->levelB->id], $tournament->activityIds());
        $this->assertSame($this->teacher->id, $tournament->instructor_id);
    }

    public function test_each_day_needs_a_level(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->tournament([], [['date' => today()->addDays(3)->toDateString(), 'activity_ids' => []]]);
    }

    public function test_eligible_student_signs_up_from_portal_and_gets_a_club_fee(): void
    {
        $student = $this->student($this->levelB);
        $tournament = $this->tournament();

        $participant = app(TournamentService::class)->selfSignup($tournament, $student);

        $this->assertFalse($participant->is_exception);
        $fee = $participant->fee;
        $this->assertSame(FeeType::Tournament, $fee->type);
        $this->assertNull($fee->instructor_id);
        $this->assertEquals('15000.00', $fee->amount);
        $this->assertSame($tournament->payment_due_date->toDateString(), $fee->due_date->toDateString());
    }

    public function test_student_of_other_level_cannot_self_sign_up_but_teacher_can_add_as_exception(): void
    {
        $other = Activity::factory()->create();
        $student = $this->student($other);
        $tournament = $this->tournament();

        try {
            app(TournamentService::class)->selfSignup($tournament, $student);
            $this->fail('No debería poder anotarse.');
        } catch (BusinessRuleException) {
        }

        $participant = app(TournamentService::class)->addParticipant($tournament, $student, $this->teacher);
        $this->assertTrue($participant->is_exception);

        // El profe puede cobrarle aunque no sea de sus niveles.
        $this->assertTrue(app(CashCollectionService::class)->isStudentOf($this->teacher, $student));
    }

    public function test_restricted_tournament_and_closed_signup(): void
    {
        $student = $this->student($this->levelA);

        $restricted = $this->tournament(['is_open' => false]);
        $this->expectException(BusinessRuleException::class);
        app(TournamentService::class)->selfSignup($restricted, $student);
    }

    public function test_signup_closes_when_tournament_starts(): void
    {
        $student = $this->student($this->levelA);
        $tournament = $this->tournament([], [['date' => today()->toDateString(), 'activity_ids' => [$this->levelA->id]]]);

        $this->expectException(BusinessRuleException::class);
        app(TournamentService::class)->selfSignup($tournament, $student);
    }

    public function test_late_addition_after_deadline_is_due_today(): void
    {
        $tournament = $this->tournament(['payment_due_date' => today()->subDay()->toDateString()]);
        $participant = app(TournamentService::class)->addParticipant($tournament, $this->student($this->levelA), $this->teacher);

        $this->assertSame(today()->toDateString(), $participant->fee->due_date->toDateString());
    }

    public function test_removing_participant_cancels_fee_but_not_if_paid(): void
    {
        $service = app(TournamentService::class);
        $tournament = $this->tournament();
        $a = $this->student($this->levelA);
        $b = $this->student($this->levelA);
        $service->addParticipant($tournament, $a, $this->teacher);
        $paid = $service->addParticipant($tournament, $b, $this->teacher);

        $service->removeParticipant($tournament, $a);
        $this->assertSame(FeeStatus::Cancelled, Fee::where('member_id', $a->id)->sole()->status);

        app(CashCollectionService::class)->collect($this->teacher, $b, [$paid->fee_id], '5000');
        $this->expectException(BusinessRuleException::class);
        $service->removeParticipant($tournament, $b);
    }

    public function test_price_change_updates_unpaid_fees_only(): void
    {
        $service = app(TournamentService::class);
        $tournament = $this->tournament();
        $unpaid = $service->addParticipant($tournament, $this->student($this->levelA), $this->teacher);
        $partial = $service->addParticipant($tournament, $payer = $this->student($this->levelA), $this->teacher);
        app(CashCollectionService::class)->collect($this->teacher, $payer, [$partial->fee_id], '1000');

        $service->save($tournament, [
            'name' => 'Primavera', 'price' => '18000.00', 'payment_due_date' => today()->addDays(6)->toDateString(), 'is_open' => true,
        ], [['date' => today()->addDays(10)->toDateString(), 'activity_ids' => [$this->levelA->id]]], $this->teacher);

        $this->assertEquals('18000.00', $unpaid->fee->fresh()->amount);
        $this->assertEquals('15000.00', $partial->fee->fresh()->amount);
    }

    public function test_teacher_collects_fee_and_tournament_together_or_separately(): void
    {
        $student = $this->student($this->levelA);
        app(FeeService::class)->generateForMember($student, today());
        $monthly = Fee::where('member_id', $student->id)->where('type', FeeType::Activity)->sole();
        $tournamentFee = app(TournamentService::class)->addParticipant($this->tournament(), $student, $this->teacher)->fee;
        $cash = app(CashCollectionService::class);

        // Solo el torneo.
        $cash->collect($this->teacher, $student, [$tournamentFee->id], '15000');
        $this->assertSame(FeeStatus::Paid, $tournamentFee->fresh()->status);
        $this->assertSame(FeeStatus::Pending, $monthly->fresh()->status);

        // Cuota + otro torneo en un mismo pago.
        $other = app(TournamentService::class)->addParticipant($this->tournament(['name' => 'Otoño', 'price' => '5000.00']), $student, $this->teacher)->fee;
        $payment = $cash->collect($this->teacher, $student, [$monthly->id, $other->id], '25000');
        $this->assertCount(2, $payment->fees);
    }

    public function test_overdue_tournament_has_no_surcharge_and_does_not_block_access(): void
    {
        Setting::set('club.surcharge_percent', 10);
        Setting::set('club.max_overdue_for_access', 1);
        $student = $this->student($this->levelA);
        $tournament = $this->tournament(['payment_due_date' => today()->addDay()->toDateString()]);
        $fee = app(TournamentService::class)->addParticipant($tournament, $student, $this->teacher)->fee;

        $this->travel(3)->days();
        app(FeeService::class)->markOverdue();

        $fee->refresh();
        $this->assertSame(FeeStatus::Overdue, $fee->status);
        $this->assertEquals('0.00', $fee->surcharge);
        $this->assertSame(0, $student->overdueFeesCount());
        $this->assertSame(AccessResult::Granted, app(AccessService::class)->evaluate($student->fresh())['result']);
    }

    public function test_cancel_tournament_cancels_unpaid_fees(): void
    {
        $service = app(TournamentService::class);
        $tournament = $this->tournament();
        $participant = $service->addParticipant($tournament, $this->student($this->levelA), $this->teacher);

        $this->assertSame(0, $service->cancel($tournament, 'Lluvia'));
        $this->assertSame(FeeStatus::Cancelled, $participant->fee->fresh()->status);
        $this->assertNotNull($tournament->fresh()->cancelled_at);
    }

    public function test_admin_screens_create_and_manage_tournament(): void
    {
        $this->actingAs($this->teacher);
        $student = $this->student($this->levelA);

        Livewire::test(TournamentForm::class)
            ->set('name', 'Copa Verano')
            ->set('price', '12000')
            ->set('payment_due_date', today()->addDays(5)->toDateString())
            ->set('days.0.date', today()->addDays(8)->toDateString())
            ->set('days.0.activity_ids', [(string) $this->levelA->id])
            ->call('save')
            ->assertHasNoErrors();

        $tournament = Tournament::where('name', 'Copa Verano')->sole();
        $this->get(route('admin.tournaments.index'))->assertOk()->assertSee('Copa Verano');

        Livewire::test(TournamentShow::class, ['tournament' => $tournament])
            ->call('addAllEligible')
            ->assertSee($student->sortableName());

        $this->assertSame(1, $tournament->participants()->count());
    }

    public function test_portal_lists_and_joins_open_tournaments(): void
    {
        $user = $this->memberUser();
        $member = $user->currentMember();
        app(EnrollmentService::class)->enroll($member, $this->levelA);
        $tournament = $this->tournament();
        $this->actingAs($user);

        Livewire::test(PortalTournaments::class)
            ->assertSee('Primavera')
            ->call('join', $tournament->id);

        $this->assertTrue($tournament->participants()->where('member_id', $member->id)->exists());
        $this->get(route('portal.tournaments'))->assertOk();
    }

    public function test_message_to_tournament_debtors_is_pushed_and_shown_in_portal(): void
    {
        $service = app(TournamentService::class);
        $tournament = $this->tournament();
        $debtorUser = $this->memberUser();
        $debtor = $debtorUser->currentMember();
        app(EnrollmentService::class)->enroll($debtor, $this->levelA);
        $payer = $this->student($this->levelA);
        $service->addParticipant($tournament, $debtor, $this->teacher);
        $paid = $service->addParticipant($tournament, $payer, $this->teacher);
        app(CashCollectionService::class)->collect($this->teacher, $payer, [$paid->fee_id], '15000');

        $this->actingAs($this->teacher);
        Livewire::test(Compose::class, ['tournamentId' => $tournament->id])
            ->assertSet('audience', MemberMessageService::AUDIENCE_TOURNAMENT)
            ->assertSee('1</strong> destinatarios', false)
            ->call('send');

        Notification::assertSentTo($debtor, MemberMessageNotification::class);
        Notification::assertNotSentTo($payer, MemberMessageNotification::class);

        $this->actingAs($debtorUser);
        Livewire::test(PortalMessages::class)->assertSee('falta tu pago');
        $this->assertNotNull($debtor->messages()->first()->pivot->read_at);
    }
}
