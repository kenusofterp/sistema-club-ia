<?php

namespace Tests\Feature;

use App\Enums\AccessResult;
use App\Enums\FeeStatus;
use App\Enums\PaymentMethod;
use App\Http\Middleware\ResolveOrganization;
use App\Livewire\Admin\Organizations\Index as OrganizationsIndex;
use App\Models\Activity;
use App\Models\Fee;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\SiteSection;
use App\Notifications\WelcomeMemberNotification;
use App\Services\AccessService;
use App\Services\MemberService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class MultiOrganizationTest extends TestCase
{
    private Organization $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = $this->makeOrganization('Gimnasio Centro', 'gimnasio', 'gimnasio');
    }

    public function test_data_is_isolated_between_organizations(): void
    {
        $clubMember = $this->activeMember(['first_name' => 'Clubista']);
        $gymMember = Organization::runFor($this->gym, fn () => $this->activeMember(['first_name' => 'Gimnasta']));

        $this->assertSame([$clubMember->id], Member::pluck('id')->all());
        Organization::runFor($this->gym, fn () => $this->assertSame([$gymMember->id], Member::pluck('id')->all()));
        $this->assertNull(Member::find($gymMember->id));
    }

    public function test_admin_screens_only_show_current_organization_and_block_foreign_ids(): void
    {
        $this->actingAs($this->staff());
        $this->activeMember(['first_name' => 'Clubista']);
        $gymMember = Organization::runFor($this->gym, fn () => $this->activeMember(['first_name' => 'Gimnasta']));

        $this->get(route('admin.members.index'))->assertOk()->assertSee('Clubista')->assertDontSee('Gimnasta');
        $this->get(route('admin.members.show', $gymMember->id))->assertNotFound();

        $this->withSession([ResolveOrganization::ADMIN_KEY => $this->gym->id])
            ->get(route('admin.members.index'))->assertOk()->assertSee('Gimnasta')->assertDontSee('Clubista');
    }

    public function test_staff_roles_are_per_organization(): void
    {
        $treasurer = $this->staff('tesorero'); // tesorero solo del club

        $this->assertSame([$this->organization->id], $treasurer->adminOrganizationIds());

        // Aunque pida la entidad del gimnasio, se queda en la suya.
        $this->actingAs($treasurer)->withSession([ResolveOrganization::ADMIN_KEY => $this->gym->id])
            ->get(route('admin.payments.index'))->assertOk()->assertSee($this->organization->name);

        $this->post(route('admin.organization.switch'), ['organization_id' => $this->gym->id])->assertForbidden();
        $this->get(route('admin.organizations'))->assertForbidden();
    }

    public function test_public_site_is_resolved_by_subdomain_and_domain(): void
    {
        Organization::runFor($this->organization, fn () => Setting::set('site.footer_text', 'Lema del club'));
        Organization::runFor($this->gym, fn () => Setting::set('site.footer_text', 'Lema del gimnasio'));
        $this->gym->update(['domain' => 'gymcentro.com']);

        $this->get('http://localhost/')->assertOk()->assertSee('Lema del club')->assertDontSee('Lema del gimnasio');
        $this->get('http://gimnasio.localhost/')->assertOk()->assertSee('Lema del gimnasio');
        $this->get('http://gymcentro.com/')->assertOk()->assertSee('Lema del gimnasio');
    }

    public function test_one_account_many_memberships_and_portal_switch(): void
    {
        $user = $this->memberUser(['email' => 'persona@example.com', 'document_number' => '30111222']);
        Organization::runFor($this->gym, fn () => $this->activeMember(['email' => 'persona@example.com', 'document_number' => '30111222', 'user_id' => $user->id, 'first_name' => 'EnElGym']));

        $this->assertCount(2, $user->membershipOrganizationIds());

        $this->actingAs($user)->get(route('portal.dashboard'))->assertOk()->assertSee($this->organization->name);
        $this->post(route('portal.organization.switch'), ['organization_id' => $this->gym->id])->assertRedirect(route('portal.dashboard'));
        $this->get(route('portal.dashboard'))->assertOk()->assertSee('EnElGym');
    }

    public function test_approving_member_in_second_organization_links_existing_account(): void
    {
        $user = $this->memberUser(['email' => 'repetido@example.com', 'document_number' => '28999888']);

        $pending = Organization::runFor($this->gym, fn () => Member::factory()->pending()->create([
            'member_category_id' => MemberCategory::factory()->create()->id,
            'email' => 'repetido@example.com',
            'document_number' => '28999888',
            'birth_date' => today()->subYears(30),
        ]));

        Organization::runFor($this->gym, fn () => app(MemberService::class)->approve($pending));

        $this->assertSame($user->id, $pending->fresh()->user_id);
        Notification::assertSentTo($user, WelcomeMemberNotification::class, fn ($n) => $n->token === null && $n->organizationId === $this->gym->id);
    }

    public function test_same_document_can_be_member_in_two_organizations(): void
    {
        $this->activeMember(['document_number' => '20202020']);
        $other = Organization::runFor($this->gym, fn () => $this->activeMember(['document_number' => '20202020']));

        $this->assertNotNull($other->id);
    }

    public function test_club_card_qr_identifies_member_at_the_gym(): void
    {
        $user = $this->memberUser(['document_number' => '33333333']);
        $clubMember = $user->member;

        Organization::runFor($this->gym, function () use ($user, $clubMember) {
            $gymMember = $this->activeMember(['document_number' => '33333333', 'user_id' => $user->id]);
            $plan = Plan::factory()->create(['price' => 0]);
            app(SubscriptionService::class)->subscribe($gymMember, $plan);

            $found = app(AccessService::class)->findMember($clubMember->verificationUrl());
            $this->assertSame($gymMember->id, $found->id);
            $this->assertSame(AccessResult::Granted, app(AccessService::class)->register($found)->result);
        });
    }

    public function test_receipt_numbers_are_independent_per_organization(): void
    {
        $pay = function () {
            $member = $this->activeMember();
            $fee = Fee::factory()->create(['member_id' => $member->id, 'amount' => 100]);

            return app(PaymentService::class)->register($member, '100', PaymentMethod::Cash, [$fee->id])->receipt_number;
        };

        $this->assertSame('R-00000001', $pay());
        $this->assertSame('R-00000002', $pay());
        $this->assertSame('R-00000001', Organization::runFor($this->gym, $pay));
    }

    public function test_scheduled_commands_use_each_organization_settings(): void
    {
        Organization::runFor($this->organization, fn () => Setting::set('club.surcharge_percent', 10));
        Organization::runFor($this->gym, fn () => Setting::set('club.surcharge_percent', 0));

        $clubFee = Fee::factory()->create(['member_id' => $this->activeMember()->id, 'amount' => 1000, 'due_date' => today()->subDay()]);
        $gymFee = Organization::runFor($this->gym, fn () => Fee::factory()->create(['member_id' => $this->activeMember()->id, 'amount' => 1000, 'due_date' => today()->subDay()]));

        Organization::setCurrent(null);
        Artisan::call('club:marcar-vencidas');

        $this->assertSame('100.00', (string) Fee::acrossOrganizations()->find($clubFee->id)->surcharge);
        $this->assertSame('0.00', (string) Fee::acrossOrganizations()->find($gymFee->id)->surcharge);
        $this->assertSame(FeeStatus::Overdue, Fee::acrossOrganizations()->find($gymFee->id)->status);
    }

    public function test_super_admin_creates_organization_empty_with_only_its_settings(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(OrganizationsIndex::class)
            ->call('create')
            ->set('name', 'Club Náutico')
            ->set('type', 'mixto')
            ->call('save')
            ->assertHasNoErrors();

        $nautico = Organization::where('slug', 'club-nautico')->firstOrFail();
        Organization::runFor($nautico, function () {
            $this->assertSame('Club Náutico', setting('site.name'));
            $this->assertFalse(MemberCategory::exists());
            $this->assertFalse(Plan::exists());
            $this->assertFalse(Activity::exists());
            $this->assertFalse(SiteSection::exists());
        });
    }
}
