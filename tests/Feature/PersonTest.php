<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Admin\Members\Form as MemberForm;
use App\Models\Fee;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Models\Organization;
use App\Models\Person;
use App\Services\MemberService;
use Livewire\Livewire;
use Tests\TestCase;

class PersonTest extends TestCase
{
    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->other = $this->makeOrganization('Club Norte', 'norte');
    }

    public function test_same_document_in_two_organizations_is_one_person(): void
    {
        $here = $this->activeMember(['document_number' => '30111222', 'first_name' => 'Ana']);
        $there = Organization::runFor($this->other, fn () => $this->activeMember(['document_number' => '30111222', 'first_name' => 'Ana']));

        $this->assertSame($here->person_id, $there->person_id);
        $this->assertSame(1, Person::count());
        $this->assertCount(2, $here->person->memberships);
    }

    public function test_new_membership_reuses_personal_data_and_account(): void
    {
        $user = $this->memberUser(['document_number' => '30111222', 'email' => 'ana@example.com', 'phone' => '1155550000']);
        $lastName = $user->currentMember()->last_name;

        $member = Organization::runFor($this->other, fn () => app(MemberService::class)->create([
            'document_type' => 'DNI',
            'document_number' => '30111222',
            'first_name' => 'Ana',
            'last_name' => $lastName,
            'birth_date' => today()->subYears(30)->toDateString(),
            'member_category_id' => MemberCategory::factory()->create()->id,
        ]));

        $this->assertSame('ana@example.com', $member->email);
        $this->assertSame('1155550000', $member->phone);
        $this->assertSame($user->id, $member->user_id);
        $this->assertCount(2, $user->membershipOrganizationIds());
    }

    public function test_personal_changes_propagate_to_every_membership(): void
    {
        $here = $this->activeMember(['document_number' => '30111222']);
        $there = Organization::runFor($this->other, fn () => $this->activeMember(['document_number' => '30111222']));

        $here->update(['phone' => '1199998888', 'city' => 'Rosario', 'notes' => 'Solo de este club']);

        $there = Member::acrossOrganizations()->find($there->id);
        $this->assertSame('1199998888', $there->phone);
        $this->assertSame('Rosario', $there->city);
        $this->assertNull($there->notes);
        $this->assertSame('Rosario', $here->person->fresh()->city);
    }

    public function test_document_of_another_person_is_rejected(): void
    {
        $this->activeMember(['document_number' => '30111222']);
        $other = Organization::runFor($this->other, fn () => $this->activeMember(['document_number' => '40111222']));

        $this->expectException(BusinessRuleException::class);
        $other->update(['document_number' => '30111222']);
    }

    public function test_form_prefills_person_from_other_organization_without_exposing_fees(): void
    {
        $there = Organization::runFor($this->other, fn () => $this->activeMember(['document_number' => '30111222', 'first_name' => 'Lucía']));
        Organization::runFor($this->other, fn () => Fee::factory()->create(['member_id' => $there->id]));

        $this->actingAs($this->staff());
        Livewire::test(MemberForm::class)
            ->set('document_number', '30111222')
            ->assertSet('first_name', 'Lucía')
            ->assertSee('ya está registrada en el sistema');

        $this->assertSame(0, Fee::count());
    }
}
