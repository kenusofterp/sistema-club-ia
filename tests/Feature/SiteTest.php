<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Livewire\Site\ContactForm;
use App\Livewire\Site\JoinForm;
use App\Models\ContactMessage;
use App\Models\HeroSlide;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Models\Setting;
use App\Models\SiteSection;
use App\Notifications\NewContactMessageNotification;
use Database\Seeders\ClubBaseSeeder;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class SiteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SiteContentSeeder::class, ClubBaseSeeder::class]);
    }

    public function test_home_page_renders_dynamic_hero_and_sections(): void
    {
        HeroSlide::query()->first()->update(['title' => 'Bienvenidos al club de prueba']);
        Setting::set('site.name', 'Club Atlético Sidkenu');

        $this->get('/')
            ->assertOk()
            ->assertSee('Bienvenidos al club de prueba')
            ->assertSee('Club Atlético Sidkenu')
            ->assertSee('Nuestro club')
            ->assertSee('Fútbol infantil');
    }

    public function test_hidden_sections_and_slides_are_not_rendered(): void
    {
        HeroSlide::query()->update(['is_active' => false]);
        HeroSlide::create(['title' => 'Diapositiva oculta', 'is_active' => false]);
        SiteSection::where('key', 'nosotros')->update(['is_active' => false]);

        $this->get('/')->assertOk()->assertDontSee('Diapositiva oculta')->assertDontSee('Nuestro club');
    }

    public function test_brand_color_from_settings_is_applied(): void
    {
        Setting::set('site.primary_color', '#123456');

        $this->get('/')->assertSee('--brand: #123456', false);
    }

    public function test_public_pages_render(): void
    {
        $this->get(route('site.activities'))->assertOk()->assertSee('Natación');
        $this->get(route('site.activity', 'natacion'))->assertOk()->assertSee('Horarios');
        $this->get(route('site.news'))->assertOk();
        $this->get(route('site.page', 'estatuto'))->assertOk()->assertSee('Estatuto social');
        $this->get(route('site.join'))->assertOk()->assertSee('Asociate al club');
        $this->get(route('offline'))->assertOk();
    }

    public function test_join_form_creates_pending_member(): void
    {
        $category = MemberCategory::where('name', 'Activo')->first();

        Livewire::test(JoinForm::class)
            ->set('first_name', 'Ana')
            ->set('last_name', 'Gómez')
            ->set('document_number', '30111222')
            ->set('birth_date', today()->subYears(30)->toDateString())
            ->set('email', 'ana@example.com')
            ->set('phone', '1155550000')
            ->set('member_category_id', $category->id)
            ->set('accept', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $member = Member::where('document_number', '30111222')->first();
        $this->assertSame(MemberStatus::Pending, $member->status);
        $this->assertNull($member->member_number);
    }

    public function test_join_form_validates_category_age(): void
    {
        $infantil = MemberCategory::where('name', 'Infantil')->first();

        Livewire::test(JoinForm::class)
            ->set('first_name', 'Juan')
            ->set('last_name', 'Pérez')
            ->set('document_number', '20111222')
            ->set('birth_date', today()->subYears(40)->toDateString())
            ->set('email', 'juan@example.com')
            ->set('phone', '1155550000')
            ->set('member_category_id', $infantil->id)
            ->set('accept', true)
            ->call('submit')
            ->assertHasErrors('member_category_id');

        $this->assertDatabaseMissing('members', ['document_number' => '20111222']);
    }

    public function test_contact_form_stores_message_and_notifies_club(): void
    {
        Livewire::test(ContactForm::class)
            ->set('name', 'Carlos')
            ->set('email', 'carlos@example.com')
            ->set('message', 'Quisiera información sobre la colonia de verano.')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $this->assertDatabaseHas('contact_messages', ['email' => 'carlos@example.com']);
        Notification::assertSentOnDemand(NewContactMessageNotification::class);
    }

    public function test_contact_form_honeypot_discards_spam(): void
    {
        Livewire::test(ContactForm::class)
            ->set('name', 'Bot')
            ->set('email', 'bot@example.com')
            ->set('message', 'Compre seguidores baratos ahora mismo')
            ->set('website', 'http://spam.test')
            ->call('send');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_pwa_manifest_and_icon(): void
    {
        $this->get(route('pwa.manifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('name', setting('site.name'));

        $this->get(route('pwa.icon', 192))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_member_card_verification_page(): void
    {
        $member = $this->activeMember();

        $this->get(route('member.verify', $member->uuid))->assertOk()->assertSee('Carnet válido')->assertSee($member->fullName());

        $member->update(['status' => MemberStatus::Suspended]);
        $this->get(route('member.verify', $member->uuid))->assertOk()->assertSee('Carnet no válido');
    }
}
