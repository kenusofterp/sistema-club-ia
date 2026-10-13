<?php

namespace Tests\Feature;

use App\Enums\FeeStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Admin\Payments\Create;
use App\Livewire\Admin\Tournaments\Show as TournamentShow;
use App\Livewire\Portal\Tournaments as PortalTournaments;
use App\Models\Activity;
use App\Models\Fee;
use App\Models\MemberCategory;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\Tournament;
use App\Models\User;
use App\Services\CashCollectionService;
use App\Services\EnrollmentService;
use App\Services\FeeService;
use App\Services\PaymentService;
use App\Services\RefundService;
use App\Services\TournamentService;
use Livewire\Livewire;
use Tests\TestCase;

/** Torneo cancelado: nadie queda debiendo y se registra la devolución de lo cobrado, si se puede. */
class TournamentRefundTest extends TestCase
{
    private User $teacher;

    private Activity $level;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('club.charge_activity_on_enroll', false);
        $this->teacher = $this->staff('profesor');
        $this->level = Activity::factory()->create(['instructor_id' => $this->teacher->id, 'monthly_fee' => 20000]);
    }

    private function student()
    {
        $member = $this->activeMember([], MemberCategory::factory()->create(['monthly_fee' => 0]));
        app(EnrollmentService::class)->enroll($member, $this->level);

        return $member;
    }

    private function tournament(): Tournament
    {
        return app(TournamentService::class)->save(null, [
            'name' => 'Primavera', 'price' => '15000.00', 'payment_due_date' => today()->addDays(5)->toDateString(), 'is_open' => true,
        ], [['date' => today()->addDays(10)->toDateString(), 'activity_ids' => [$this->level->id]]], $this->teacher);
    }

    public function test_cancelling_leaves_no_debt_even_for_partial_payments(): void
    {
        $tournament = $this->tournament();
        $partial = $this->student();
        $fee = app(TournamentService::class)->addParticipant($tournament, $partial, $this->teacher)->fee;
        app(CashCollectionService::class)->collect($this->teacher, $partial, [$fee->id], '5000');

        $this->assertSame(1, app(TournamentService::class)->cancel($tournament, 'Lluvia'));

        $fee->refresh();
        $this->assertSame(FeeStatus::Cancelled, $fee->status);
        $this->assertEquals('5000.00', $fee->paid_amount);
        $this->assertSame(0, bccomp($partial->balance(), '0', 2));
    }

    public function test_refund_full_or_partial_and_not_more_than_paid(): void
    {
        $tournament = $this->tournament();
        $student = $this->student();
        $fee = app(TournamentService::class)->addParticipant($tournament, $student, $this->teacher)->fee;
        app(CashCollectionService::class)->collect($this->teacher, $student, [$fee->id], '15000');
        $refunds = app(RefundService::class);

        // Antes de cancelar no se puede devolver.
        $this->assertEquals('0.00', $refunds->refundable($fee));

        app(TournamentService::class)->cancel($tournament, 'Suspendido');
        $fee->refresh();
        $this->assertEquals('15000.00', $refunds->refundable($fee));

        $refunds->refund($fee, '10000.00', PaymentMethod::Transfer, $this->teacher, notes: 'CBU');
        $this->assertEquals('5000.00', $fee->fresh()->paid_amount);

        try {
            $refunds->refund($fee->fresh(), '6000.00', PaymentMethod::Cash, $this->teacher);
            $this->fail('No debería devolver más de lo pagado.');
        } catch (BusinessRuleException) {
        }

        $refunds->refund($fee->fresh(), '5000.00', PaymentMethod::Cash, $this->teacher);
        $this->assertEquals('0.00', $fee->fresh()->paid_amount);
        $this->assertEquals(15000, Refund::where('fee_id', $fee->id)->sum('amount'));

        // El pago original queda (el dinero entró); no se puede anular después de devolver.
        $this->expectException(BusinessRuleException::class);
        app(PaymentService::class)->cancel(Payment::sole(), 'Error');
    }

    public function test_payment_with_monthly_fee_and_tournament_keeps_the_monthly_fee_paid(): void
    {
        $tournament = $this->tournament();
        $student = $this->student();
        app(FeeService::class)->generateForMember($student, today());
        $monthly = Fee::where('member_id', $student->id)->whereNull('tournament_id')->sole();
        $fee = app(TournamentService::class)->addParticipant($tournament, $student, $this->teacher)->fee;
        app(CashCollectionService::class)->collect($this->teacher, $student, [$monthly->id, $fee->id], '35000');

        app(TournamentService::class)->cancel($tournament, 'Suspendido');
        app(RefundService::class)->refund($fee->fresh(), '15000.00', PaymentMethod::Cash, $this->teacher);

        $this->assertSame(FeeStatus::Paid, $monthly->fresh()->status);
        $this->assertEquals('0.00', $fee->fresh()->paid_amount);
    }

    public function test_money_left_on_account_pays_the_next_monthly_fee_when_chosen(): void
    {
        $tournament = $this->tournament();
        $student = $this->student();
        $fee = app(TournamentService::class)->addParticipant($tournament, $student, $this->teacher)->fee;
        app(CashCollectionService::class)->collect($this->teacher, $student, [$fee->id], '15000');
        app(TournamentService::class)->cancel($tournament, 'Suspendido');

        app(RefundService::class)->toCredit($fee->fresh(), '15000.00', $this->teacher);
        $this->assertEquals('15000.00', $student->creditBalance());
        $this->assertEquals('0.00', $fee->fresh()->paid_amount);

        // La cuota de $ 20.000 no se paga sola: queda pendiente hasta que se elige usar el saldo.
        app(FeeService::class)->generateForMember($student, today());
        $monthly = Fee::where('member_id', $student->id)->whereNull('tournament_id')->sole();
        $this->assertEquals('0.00', $monthly->paid_amount);

        // El profe usa el saldo: paga $ 15.000 y quedan $ 5.000 pendientes.
        $payment = app(CashCollectionService::class)->payWithCredit($this->teacher, $student, [$monthly->id], '15000.00');
        $this->assertSame(PaymentMethod::AccountCredit, $payment->method);
        $this->assertTrue($monthly->fresh()->isOpen());
        $this->assertEquals('15000.00', $monthly->fresh()->paid_amount);
        $this->assertEquals('0.00', $student->creditBalance());

        // No es efectivo: no entra en lo que el profe tiene que rendir.
        $this->assertEquals('15000.00', app(CashCollectionService::class)->pendingTotal($this->teacher));

        // Sin saldo no se puede volver a usar.
        try {
            app(CashCollectionService::class)->payWithCredit($this->teacher, $student, [$monthly->id], '1000.00');
            $this->fail('No debería pagar sin saldo.');
        } catch (BusinessRuleException) {
        }

        // Si se anula el pago con saldo, el saldo vuelve.
        app(PaymentService::class)->cancel($payment, 'Error');
        $this->assertEquals('15000.00', $student->creditBalance());
        $this->assertEquals('0.00', $monthly->fresh()->paid_amount);
    }

    public function test_admin_payment_screen_offers_account_credit(): void
    {
        $tournament = $this->tournament();
        $student = $this->student();
        $fee = app(TournamentService::class)->addParticipant($tournament, $student, $this->teacher)->fee;
        app(CashCollectionService::class)->collect($this->teacher, $student, [$fee->id], '15000');
        app(TournamentService::class)->cancel($tournament, 'Suspendido');
        app(RefundService::class)->toCredit($fee->fresh(), '15000.00', $this->teacher);
        app(FeeService::class)->generateForMember($student, today());

        $this->actingAs($this->staff());
        Livewire::test(Create::class, ['memberId' => $student->id])
            ->assertSee('de saldo a favor')
            ->set('method', 'saldo')
            ->assertSet('amount', '15000.00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('0.00', $student->creditBalance());
        $this->get(route('admin.members.show', $student))->assertOk()->assertSee('Saldo a favor');
    }

    public function test_tournament_cancelled_before_the_change_still_allows_refund_or_credit(): void
    {
        $tournament = $this->tournament();
        $student = $this->student();
        $fee = app(TournamentService::class)->addParticipant($tournament, $student, $this->teacher)->fee;
        app(CashCollectionService::class)->collect($this->teacher, $student, [$fee->id], '15000');
        // Como quedaban antes: torneo cancelado pero el cargo pagado seguía "pagada".
        $tournament->update(['cancelled_at' => now(), 'cancel_reason' => 'Lluvia']);
        $this->assertSame(FeeStatus::Paid, $fee->fresh()->status);

        $this->assertEquals('15000.00', app(RefundService::class)->refundable($fee->fresh()));
        app(RefundService::class)->toCredit($fee->fresh(), '15000.00', $this->teacher);

        $this->assertSame(FeeStatus::Cancelled, $fee->fresh()->status);
        $this->assertEquals('15000.00', $student->creditBalance());
    }

    public function test_choosing_account_credit_defaults_to_the_amount_paid(): void
    {
        $tournament = $this->tournament();
        $student = $this->student();
        $fee = app(TournamentService::class)->addParticipant($tournament, $student, $this->teacher)->fee;
        app(CashCollectionService::class)->collect($this->teacher, $student, [$fee->id], '15000');
        app(TournamentService::class)->cancel($tournament, 'Lluvia');

        $this->actingAs($this->teacher);
        Livewire::test(TournamentShow::class, ['tournament' => $tournament])
            ->call('askRefund', $fee->id)
            ->set('refundAmount', '1000')
            ->set('refundDestination', 'a_cuenta')
            ->assertSet('refundAmount', '15000.00')
            ->assertSee('Pagó $ 15.000,00')
            ->call('confirmRefund')
            ->assertHasNoErrors();

        $this->assertEquals('15000.00', $student->creditBalance());
    }

    public function test_screens_register_and_show_the_refund(): void
    {
        $tournament = $this->tournament();
        $user = $this->memberUser();
        $member = $user->currentMember();
        app(EnrollmentService::class)->enroll($member, $this->level);
        $fee = app(TournamentService::class)->addParticipant($tournament, $member, $this->teacher)->fee;
        app(CashCollectionService::class)->collect($this->teacher, $member, [$fee->id], '15000');

        $this->actingAs($this->teacher);
        Livewire::test(TournamentShow::class, ['tournament' => $tournament])
            ->set('cancelReason', 'Lluvia')
            ->call('cancelTournament')
            ->assertSee('Devolver o dejar a cuenta')
            ->call('askRefund', $fee->id)
            ->assertSet('refundAmount', '15000.00')
            ->set('refundMethod', 'transferencia')
            ->call('confirmRefund')
            ->assertHasNoErrors()
            ->assertSee('devuelto');

        $this->assertSame(PaymentMethod::Transfer, Refund::sole()->method);
        $this->get(route('admin.members.show', $member))->assertOk()->assertSee('Devoluciones');

        $this->actingAs($user);
        Livewire::test(PortalTournaments::class)->assertSee('Te devolvimos');
    }
}
