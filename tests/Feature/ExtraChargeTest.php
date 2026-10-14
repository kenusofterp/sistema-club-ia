<?php

namespace Tests\Feature;

use App\Enums\AccessResult;
use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Admin\ExtraCharges\Form as ExtraChargeForm;
use App\Livewire\Admin\ExtraCharges\Show as ExtraChargeShow;
use App\Livewire\Admin\MemberMessages\Compose;
use App\Models\Activity;
use App\Models\ExtraCharge;
use App\Models\Fee;
use App\Models\MemberCategory;
use App\Models\Setting;
use App\Models\Tournament;
use App\Models\User;
use App\Notifications\MemberMessageNotification;
use App\Services\AccessService;
use App\Services\CashCollectionService;
use App\Services\EnrollmentService;
use App\Services\ExtraChargeService;
use App\Services\FeeService;
use App\Services\TournamentService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Vestimenta / artículos que el profe cobra a los alumnos elegidos y anula a quien ya lo consiguió. */
class ExtraChargeTest extends TestCase
{
    private User $teacher;

    private Activity $level;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('club.charge_activity_on_enroll', false);
        $this->teacher = $this->staff('profesor');
        $this->level = Activity::factory()->create(['name' => 'Juvenil A', 'instructor_id' => $this->teacher->id, 'monthly_fee' => 20000]);
    }

    private function student()
    {
        $member = $this->activeMember([], MemberCategory::factory()->create(['monthly_fee' => 0]));
        app(EnrollmentService::class)->enroll($member, $this->level);

        return $member;
    }

    private function tournamentWith(array $members): Tournament
    {
        $tournament = app(TournamentService::class)->save(null, [
            'name' => 'Primavera', 'price' => '15000.00', 'payment_due_date' => today()->addDays(5)->toDateString(), 'is_open' => true,
        ], [['date' => today()->addDays(10)->toDateString(), 'activity_ids' => [$this->level->id]]], $this->teacher);

        foreach ($members as $member) {
            app(TournamentService::class)->addParticipant($tournament, $member, $this->teacher);
        }

        return $tournament;
    }

    private function malla(Tournament $tournament, $members): ExtraCharge
    {
        return app(ExtraChargeService::class)->create([
            'name' => 'Malla', 'price' => '25000.00', 'due_date' => today()->addDays(5)->toDateString(), 'tournament_id' => $tournament->id,
        ], collect($members), $this->teacher);
    }

    public function test_tournament_outfit_is_charged_only_to_checked_students(): void
    {
        [$a, $b, $c] = [$this->student(), $this->student(), $this->student()];
        $tournament = $this->tournamentWith([$a, $b, $c]);
        $this->actingAs($this->teacher);

        // Todos tildados por defecto; se destilda a uno.
        Livewire::test(ExtraChargeForm::class, ['tournamentId' => $tournament->id])
            ->assertSet('origin', 'torneo')
            ->assertCount('selected', 3)
            ->set('name', 'Malla')
            ->set('price', '25000')
            ->set('selected', [(string) $a->id, (string) $b->id])
            ->call('save')
            ->assertHasNoErrors();

        $charge = ExtraCharge::sole();
        $this->assertSame($tournament->id, $charge->tournament_id);
        $fees = Fee::where('extra_charge_id', $charge->id)->get();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $fees->pluck('member_id')->all());
        $this->assertTrue($fees->every(fn (Fee $f) => $f->type === FeeType::Item && $f->instructor_id === null && $f->concept === 'Malla - Torneo Primavera'));
    }

    public function test_outfit_due_date_cannot_be_after_the_tournament(): void
    {
        $a = $this->student();
        $tournament = $this->tournamentWith([$a]); // empieza en 10 días
        $service = app(ExtraChargeService::class);

        // El día del torneo se acepta.
        $charge = $service->create(['name' => 'Malla', 'price' => '25000.00', 'due_date' => today()->addDays(10)->toDateString(), 'tournament_id' => $tournament->id], collect([$a]), $this->teacher);

        // Al editar tampoco se puede pasar de la fecha del torneo.
        try {
            $service->update($charge, ['name' => 'Malla', 'price' => '25000.00', 'due_date' => today()->addDays(11)->toDateString()]);
            $this->fail('Debería rechazar una fecha posterior al torneo.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('posterior al torneo', $e->getMessage());
        }

        // En la pantalla se informa el error y no se carga nada.
        $this->actingAs($this->teacher);
        Livewire::test(ExtraChargeForm::class, ['tournamentId' => $tournament->id])
            ->set('name', 'Remera')
            ->set('price', '9000')
            ->set('due_date', today()->addDays(15)->toDateString())
            ->assertSee('max="'.today()->addDays(10)->toDateString().'"', false)
            ->call('save');
        $this->assertSame(0, ExtraCharge::where('name', 'Remera')->count());
    }

    public function test_cancel_for_one_student_who_got_it_elsewhere(): void
    {
        [$a, $b] = [$this->student(), $this->student()];
        $charge = $this->malla($this->tournamentWith([$a, $b]), [$a, $b]);
        $this->actingAs($this->teacher);

        Livewire::test(ExtraChargeShow::class, ['extraCharge' => $charge])
            ->call('askCancel', $a->id)
            ->set('cancelReason', 'La consiguió por otro lado')
            ->call('confirmCancel')
            ->assertSee('La consiguió por otro lado');

        $this->assertSame(FeeStatus::Cancelled, Fee::where('extra_charge_id', $charge->id)->where('member_id', $a->id)->sole()->status);
        $this->assertSame(FeeStatus::Pending, Fee::where('extra_charge_id', $charge->id)->where('member_id', $b->id)->sole()->status);
    }

    public function test_list_filters_who_owes_and_who_paid(): void
    {
        [$a, $b, $c] = [$this->student(), $this->student(), $this->student()];
        $charge = $this->malla($this->tournamentWith([$a, $b, $c]), [$a, $b, $c]);
        app(CashCollectionService::class)->collect($this->teacher, $a, [Fee::where('extra_charge_id', $charge->id)->where('member_id', $a->id)->value('id')], '25000');
        app(ExtraChargeService::class)->cancelFor($charge, $c, 'Ya la tenía');
        $this->actingAs($this->teacher);

        $names = fn (string $filter) => Livewire::withQueryParams(['ver' => $filter])->test(ExtraChargeShow::class, ['extraCharge' => $charge])
            ->viewData('rows')->pluck('member_id')->all();

        $this->assertSame([$b->id], $names('deben'));
        $this->assertSame([$a->id], $names('pagaron'));
        $this->assertSame([$c->id], $names('anulados'));
        $this->assertCount(3, $names('todos'));
    }

    public function test_cannot_cancel_if_already_paid(): void
    {
        $a = $this->student();
        $charge = $this->malla($this->tournamentWith([$a]), [$a]);
        $fee = Fee::where('extra_charge_id', $charge->id)->sole();
        app(CashCollectionService::class)->collect($this->teacher, $a, [$fee->id], '1000');

        $this->expectException(BusinessRuleException::class);
        app(ExtraChargeService::class)->cancelFor($charge, $a, 'Ya la tenía');
    }

    public function test_add_members_does_not_duplicate_and_can_recharge_after_cancel(): void
    {
        [$a, $b] = [$this->student(), $this->student()];
        $charge = $this->malla($this->tournamentWith([$a, $b]), [$a]);
        $service = app(ExtraChargeService::class);

        $this->assertSame(1, $service->addMembers($charge, collect([$a, $b])));
        $service->cancelFor($charge, $b, 'Error');
        $this->assertSame(1, $service->addMembers($charge, collect([$b])));
        $this->assertSame(2, $service->summary($charge)['charged']);
    }

    public function test_level_item_and_price_change_only_on_unpaid(): void
    {
        [$a, $b] = [$this->student(), $this->student()];
        $service = app(ExtraChargeService::class);
        $charge = $service->create(['name' => 'Remera', 'price' => '10000.00', 'due_date' => today()->addDays(3)->toDateString(), 'activity_id' => $this->level->id], collect([$a, $b]), $this->teacher);
        $paid = Fee::where('extra_charge_id', $charge->id)->where('member_id', $a->id)->sole();
        app(CashCollectionService::class)->collect($this->teacher, $a, [$paid->id], '10000');

        $service->update($charge, ['name' => 'Remera', 'price' => '12000.00', 'due_date' => today()->addDays(3)->toDateString()]);

        $this->assertEquals('10000.00', $paid->fresh()->amount);
        $this->assertEquals('12000.00', Fee::where('extra_charge_id', $charge->id)->where('member_id', $b->id)->sole()->amount);
        $this->assertSame('Remera - Juvenil A', $paid->fresh()->concept);
    }

    public function test_teacher_collects_fee_tournament_and_outfit_together(): void
    {
        $a = $this->student();
        app(FeeService::class)->generateForMember($a, today());
        $tournament = $this->tournamentWith([$a]);
        $this->malla($tournament, [$a]);

        $ids = Fee::where('member_id', $a->id)->pluck('id')->all();
        $this->assertCount(3, $ids);
        $payment = app(CashCollectionService::class)->collect($this->teacher, $a, $ids, '60000');

        $this->assertCount(3, $payment->fees);
        $this->assertTrue(Fee::whereIn('id', $ids)->get()->every(fn (Fee $f) => $f->status === FeeStatus::Paid));
    }

    public function test_overdue_outfit_has_no_surcharge_and_does_not_block_access(): void
    {
        Setting::set('club.surcharge_percent', 10);
        Setting::set('club.max_overdue_for_access', 1);
        $a = $this->student();
        $charge = $this->malla($this->tournamentWith([$a]), [$a]);

        $this->travel(10)->days();
        app(FeeService::class)->markOverdue();

        $fee = Fee::where('extra_charge_id', $charge->id)->sole();
        $this->assertSame(FeeStatus::Overdue, $fee->status);
        $this->assertEquals('0.00', $fee->surcharge);
        $this->assertSame(AccessResult::Granted, app(AccessService::class)->evaluate($a->fresh())['result']);
    }

    public function test_teacher_only_sees_own_items_and_screens_render(): void
    {
        $a = $this->student();
        $charge = $this->malla($this->tournamentWith([$a]), [$a]);
        $other = $this->staff('profesor');
        $foreign = app(ExtraChargeService::class)->create(['name' => 'Buzo ajeno', 'price' => '5000.00', 'due_date' => today()->toDateString()], collect([$a]), $other);

        $this->actingAs($this->teacher);
        $this->get(route('admin.extra-charges.index'))->assertOk()->assertSee('Malla')->assertDontSee('Buzo ajeno');
        $this->get(route('admin.extra-charges.create'))->assertOk();
        $this->get(route('admin.extra-charges.show', $charge))->assertOk();
        $this->get(route('admin.extra-charges.show', $foreign))->assertNotFound();
        $this->get(route('admin.tournaments.show', $charge->tournament_id))->assertOk()->assertSee('Vestimenta y adicionales')->assertSee('Malla');
    }

    public function test_message_to_outfit_debtors(): void
    {
        $user = $this->memberUser();
        $debtor = $user->currentMember();
        app(EnrollmentService::class)->enroll($debtor, $this->level);
        $charge = $this->malla($this->tournamentWith([$debtor]), [$debtor]);

        $this->actingAs($this->teacher);
        Livewire::test(Compose::class, ['extraChargeId' => $charge->id])
            ->assertSet('audience', 'adicional')
            ->assertSet('title', 'Malla: falta tu pago')
            ->call('send');

        Notification::assertSentTo($debtor, MemberMessageNotification::class);
    }
}
