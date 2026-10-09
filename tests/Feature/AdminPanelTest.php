<?php

namespace Tests\Feature;

use App\Enums\FeeStatus;
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Livewire\Admin\Fees\Index as FeesIndex;
use App\Livewire\Admin\Members\Form as MemberForm;
use App\Livewire\Admin\Members\Show as MemberShow;
use App\Livewire\Admin\Payments\Create as PaymentCreate;
use App\Livewire\Admin\Roles\Index as RolesIndex;
use App\Livewire\Admin\Settings\SiteSettings;
use App\Livewire\Admin\Site\HeroSlides;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Models\Activity;
use App\Models\Fee;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\PaymentService;
use Database\Seeders\ClubBaseSeeder;
use Database\Seeders\SiteContentSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SiteContentSeeder::class, ClubBaseSeeder::class]);
    }

    public function test_every_admin_screen_renders_for_super_admin(): void
    {
        $member = $this->activeMember();
        $fee = Fee::factory()->create(['member_id' => $member->id]);
        $payment = app(PaymentService::class)->register($member, '100', PaymentMethod::Cash, [$fee->id]);
        $this->actingAs($this->staff());

        $routes = [
            'admin.dashboard', 'admin.profile', 'admin.members.index', 'admin.members.create', 'admin.categories', 'admin.access',
            'admin.activities.index', 'admin.activities.create', 'admin.enrollments', 'admin.fees', 'admin.payments.index',
            'admin.payments.create', 'admin.facilities', 'admin.reservations', 'admin.messages', 'admin.announcements',
            'admin.site.settings', 'admin.site.hero', 'admin.site.sections', 'admin.site.posts.index', 'admin.site.posts.create',
            'admin.site.pages', 'admin.users', 'admin.roles', 'admin.audit', 'admin.settings',
        ];

        foreach ($routes as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->get(route('admin.members.show', $member))->assertOk()->assertSee($member->fullName());
        $this->get(route('admin.members.edit', $member))->assertOk();
        $this->get(route('admin.activities.edit', Activity::first()->id))->assertOk();
        $this->get(route('admin.payments.receipt', $payment))->assertOk()->assertSee($payment->receipt_number);
        $this->get(route('admin.export', 'socios'))->assertOk()->assertDownload();
    }

    public function test_sidebar_only_shows_permitted_sections(): void
    {
        $this->actingAs($this->staff('tesorero'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Cuotas y cargos')
            ->assertDontSee('Roles y permisos')
            ->assertDontSee('Identidad y contacto');
    }

    public function test_permissions_are_enforced_per_screen(): void
    {
        $this->actingAs($this->staff('tesorero'));

        $this->get(route('admin.payments.index'))->assertOk();
        $this->get(route('admin.users'))->assertForbidden();
        $this->get(route('admin.roles'))->assertForbidden();
        $this->get(route('admin.site.hero'))->assertForbidden();
        $this->get(route('admin.members.create'))->assertForbidden();
    }

    public function test_livewire_actions_check_permissions(): void
    {
        $this->actingAs($this->staff('recepcion'));

        Livewire::test(FeesIndex::class)->call('processOverdue')->assertForbidden();
    }

    public function test_member_can_be_created_from_admin_form(): void
    {
        $this->actingAs($this->staff('secretaria'));
        $category = MemberCategory::where('name', 'Activo')->first();

        Livewire::test(MemberForm::class)
            ->set('first_name', 'Lucía')
            ->set('last_name', 'Fernández')
            ->set('document_number', '35123456')
            ->set('birth_date', today()->subYears(25)->toDateString())
            ->set('email', 'lucia@example.com')
            ->set('member_category_id', $category->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $member = Member::where('document_number', '35123456')->first();
        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertNotNull($member->user_id);
    }

    public function test_duplicate_document_is_rejected(): void
    {
        $this->actingAs($this->staff());
        $existing = $this->activeMember();

        Livewire::test(MemberForm::class)
            ->set('first_name', 'X')
            ->set('last_name', 'Y')
            ->set('document_number', $existing->document_number)
            ->set('birth_date', today()->subYears(25)->toDateString())
            ->set('member_category_id', $existing->member_category_id)
            ->call('save')
            ->assertHasErrors('document_number');
    }

    public function test_payment_screen_preselects_open_fees_and_registers(): void
    {
        $this->actingAs($this->staff('tesorero'));
        $member = $this->activeMember();
        $fees = Fee::factory()->count(2)->create(['member_id' => $member->id, 'amount' => 1000]);

        Livewire::withQueryParams(['socio' => $member->id])
            ->test(PaymentCreate::class)
            ->assertSet('amount', '2000.00')
            ->set('method', 'transferencia')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.members.show', $member));

        $this->assertSame(1, Payment::count());
        $this->assertTrue($fees->every(fn ($f) => $f->fresh()->status === FeeStatus::Paid));
    }

    public function test_member_show_approve_and_suspend_flow(): void
    {
        $this->actingAs($this->staff());
        $member = Member::factory()->pending()->create(['member_category_id' => MemberCategory::first()->id, 'birth_date' => today()->subYears(30)]);

        Livewire::test(MemberShow::class, ['member' => $member])
            ->call('approve')
            ->call('openStatus', 'suspend')
            ->set('statusReason', 'Falta de pago')
            ->call('confirmStatus')
            ->assertHasNoErrors();

        $this->assertSame(MemberStatus::Suspended, $member->fresh()->status);
    }

    public function test_site_settings_update_identity(): void
    {
        $this->actingAs($this->staff('comunicacion'));
        $setting = Setting::where('key', 'site.name')->first();
        $color = Setting::where('key', 'site.primary_color')->first();

        Livewire::test(SiteSettings::class)
            ->set("values.{$setting->id}", 'Club Sidkenu')
            ->set("values.{$color->id}", '#1d4ed8')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Club Sidkenu', setting('site.name'));
        $this->get('/')->assertSee('Club Sidkenu')->assertSee('--brand: #1d4ed8', false);
    }

    public function test_invalid_color_is_rejected(): void
    {
        $this->actingAs($this->staff());
        $color = Setting::where('key', 'site.primary_color')->first();

        Livewire::test(SiteSettings::class)->set("values.{$color->id}", 'rojo')->call('save')->assertHasErrors("values.{$color->id}");
    }

    public function test_hero_slide_can_be_created(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(HeroSlides::class)
            ->call('create')
            ->set('title', 'Temporada de verano 2027')
            ->set('button_text', 'Inscribite')
            ->set('button_url', '/asociate')
            ->call('save')
            ->assertHasNoErrors();

        $this->get('/')->assertSee('Temporada de verano 2027');
    }

    public function test_role_permissions_can_be_edited_but_system_roles_are_protected(): void
    {
        $this->actingAs($this->staff());
        $tesorero = Role::findByName('tesorero');

        Livewire::test(RolesIndex::class)
            ->call('selectRole', $tesorero->id)
            ->set('permissions', ['dashboard.ver', 'usuarios.gestionar'])
            ->call('save');

        $this->assertTrue($tesorero->fresh()->hasPermissionTo('usuarios.gestionar'));

        // Un rol en uso no se puede eliminar.
        $this->staff('tesorero');
        Livewire::test(RolesIndex::class)->call('selectRole', $tesorero->id)->call('deleteRole');
        $this->assertNotNull(Role::find($tesorero->id));
    }

    public function test_super_admins_are_hidden_from_the_users_list(): void
    {
        $super = $this->staff();
        $super->update(['name' => 'Plataforma Oculta']);
        $this->staff('tesorero')->update(['name' => 'Tesorera Visible']);

        $this->actingAs($super);
        Livewire::test(UsersIndex::class)
            ->assertSee('Tesorera Visible')
            ->assertDontSee('Plataforma Oculta');

        // Tampoco se lo puede abrir para editar desde esta pantalla.
        Livewire::test(UsersIndex::class)->call('edit', $super->id)->assertNotFound();
    }
}
