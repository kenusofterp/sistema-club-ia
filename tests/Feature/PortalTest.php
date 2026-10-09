<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Livewire\Portal\Activities;
use App\Livewire\Portal\Profile;
use App\Livewire\Portal\Reservations;
use App\Models\Activity;
use App\Models\Announcement;
use App\Models\Facility;
use App\Models\Fee;
use App\Services\PaymentService;
use Livewire\Livewire;
use Tests\TestCase;

class PortalTest extends TestCase
{
    public function test_portal_screens_render(): void
    {
        $user = $this->memberUser();
        Announcement::create(['title' => 'Asamblea anual', 'body' => 'El sábado.', 'published_at' => now()->subHour()]);
        Fee::factory()->create(['member_id' => $user->member->id, 'concept' => 'Cuota de prueba', 'amount' => 1234]);
        $this->actingAs($user);

        $this->get(route('portal.dashboard'))->assertOk()->assertSee('Asamblea anual')->assertSee($user->member->first_name);
        $this->get(route('portal.fees'))->assertOk()->assertSee('Cuota de prueba');
        $this->get(route('portal.activities'))->assertOk();
        $this->get(route('portal.tournaments'))->assertOk();
        $this->get(route('portal.messages'))->assertOk();
        $this->get(route('portal.reservations'))->assertOk();
        $this->get(route('portal.card'))->assertOk()->assertSee('<svg', false)->assertSee($user->member->member_number);
        $this->get(route('portal.profile'))->assertOk();
    }

    public function test_member_can_only_see_own_receipts(): void
    {
        $user = $this->memberUser();
        $other = $this->activeMember();
        $fee = Fee::factory()->create(['member_id' => $other->id, 'amount' => 100]);
        $payment = app(PaymentService::class)->register($other, '100', PaymentMethod::Cash, [$fee->id]);

        $this->actingAs($user)->get(route('portal.receipt', $payment))->assertForbidden();
    }

    public function test_member_enrolls_from_portal(): void
    {
        $user = $this->memberUser();
        $activity = Activity::factory()->create();

        Livewire::actingAs($user)->test(Activities::class)->call('enroll', $activity->id);

        $this->assertSame(1, $user->member->activeEnrollments()->count());
    }

    public function test_member_books_from_portal(): void
    {
        $user = $this->memberUser();
        $facility = Facility::factory()->create();

        Livewire::actingAs($user)->test(Reservations::class)
            ->set('facilityId', $facility->id)
            ->set('date', today()->addDay()->toDateString())
            ->call('book', '10:00');

        $this->assertSame(1, $user->member->reservations()->count());
    }

    public function test_member_updates_contact_data_but_not_identity(): void
    {
        $user = $this->memberUser();
        $document = $user->member->document_number;

        Livewire::actingAs($user)->test(Profile::class)->set('phone', '1199998888')->call('save')->assertHasNoErrors();

        $this->assertSame('1199998888', $user->member->fresh()->phone);
        $this->assertSame($document, $user->member->fresh()->document_number);
    }
}
