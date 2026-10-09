<?php

namespace Tests\Feature;

use App\Livewire\Admin\Members\Form as MemberForm;
use App\Models\Member;
use App\Models\MemberCategory;
use Database\Seeders\ClubBaseSeeder;
use Livewire\Livewire;
use Tests\TestCase;

class MemberQuickCategoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClubBaseSeeder::class);
    }

    public function test_category_is_created_from_the_member_form_and_selected(): void
    {
        $this->actingAs($this->staff());

        $component = Livewire::test(MemberForm::class)
            ->assertSeeHtml('wire:click="openCategoryModal"')
            ->call('openCategoryModal')
            ->assertSet('showCategoryModal', true)
            ->set('newCategory.name', 'Activo')
            ->call('saveCategory')
            ->assertHasErrors(['newCategory.name' => 'unique'])
            ->set('newCategory.name', 'Mayores de 65')
            ->set('newCategory.monthly_fee', '0')
            ->set('newCategory.admission_fee', '0')
            ->set('newCategory.min_age', 65)
            ->set('newCategory.max_age', '')
            ->call('saveCategory')
            ->assertHasNoErrors()
            ->assertSet('showCategoryModal', false);

        $category = MemberCategory::where('name', 'Mayores de 65')->first();
        $this->assertTrue($category->is_active);
        $this->assertSame(65, $category->min_age);
        $this->assertNull($category->max_age);
        $component->assertSet('member_category_id', $category->id);

        // El socio se registra con la categoría recién creada.
        $component
            ->set('first_name', 'Rosa')
            ->set('last_name', 'Gómez')
            ->set('document_number', '10222333')
            ->set('birth_date', today()->subYears(70)->toDateString())
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame($category->id, Member::where('document_number', '10222333')->first()->member_category_id);
    }

    public function test_quick_category_requires_permission(): void
    {
        $this->actingAs($this->staff('secretaria'));

        Livewire::test(MemberForm::class)
            ->assertDontSeeHtml('wire:click="openCategoryModal"')
            ->call('openCategoryModal')
            ->assertForbidden();
    }
}
