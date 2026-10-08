<?php

namespace Tests\Feature;

use App\Livewire\Admin\Organizations\Index as OrganizationsIndex;
use App\Models\Organization;
use Livewire\Livewire;
use Tests\TestCase;

/** Instalación desde cero: solo existe el super administrador, sin entidades. */
class FreshInstallTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->organization->forceDelete();
        Organization::setCurrent(null);
    }

    public function test_super_admin_without_organizations_is_sent_to_create_the_first_one(): void
    {
        $admin = $this->staff();

        $this->assertTrue($admin->canAccessAdmin());
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('admin.organizations'));
        $this->get(route('admin.members.index'))->assertRedirect(route('admin.organizations'));
        $this->get(route('admin.organizations'))->assertOk()->assertSee('Crear la primera entidad');
        $this->get(route('admin.help'))->assertOk()->assertSee('Manual de uso');
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_first_organization_becomes_default_and_opens_its_dashboard(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(OrganizationsIndex::class)
            ->call('create')
            ->set('name', 'Club Atlético Ejemplo')
            ->set('type', 'club')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.dashboard'));

        $organization = Organization::where('slug', 'club-atletico-ejemplo')->firstOrFail();
        $this->assertTrue($organization->is_default);

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Recién empezás');
    }
}
