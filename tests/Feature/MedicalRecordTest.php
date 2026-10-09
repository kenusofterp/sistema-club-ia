<?php

namespace Tests\Feature;

use App\Enums\MedicalFieldType;
use App\Livewire\Admin\MedicalForm\Index as MedicalFormIndex;
use App\Livewire\Admin\Members\Form as MemberForm;
use App\Livewire\Admin\Members\MedicalRecord as MedicalRecordTab;
use App\Models\MedicalFormField;
use App\Models\MedicalRecord;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Services\MedicalRecordService;
use Database\Seeders\ClubBaseSeeder;
use Livewire\Livewire;
use Tests\TestCase;

class MedicalRecordTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClubBaseSeeder::class);
    }

    private function field(array $attributes): MedicalFormField
    {
        return app(MedicalRecordService::class)->saveField(null, [
            'type' => MedicalFieldType::Text->value,
            ...$attributes,
        ]);
    }

    public function test_medical_form_is_built_from_the_admin_screen(): void
    {
        $this->actingAs($this->staff());
        $this->get(route('admin.medical-form'))->assertOk();

        Livewire::test(MedicalFormIndex::class)
            ->call('create')
            ->set('label', 'Grupo sanguíneo')
            ->set('type', MedicalFieldType::Select->value)
            ->set('section', 'Datos generales')
            ->set('optionsText', "0+\n0-\n\nA+\n0+")
            ->set('is_required', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->call('create')
            ->set('label', '¿Tiene alergias?')
            ->set('type', MedicalFieldType::YesNo->value)
            ->call('save')
            ->assertSee('Grupo sanguíneo')
            ->assertSee('¿Tiene alergias?');

        $blood = MedicalFormField::where('label', 'Grupo sanguíneo')->first();
        $this->assertSame(['0+', '0-', 'A+'], $blood->options);
        $this->assertTrue($blood->is_required);
        $this->assertSame('Datos generales', $blood->section);

        // Una lista necesita al menos dos opciones.
        Livewire::test(MedicalFormIndex::class)
            ->call('create')
            ->set('label', 'Obra social')
            ->set('type', MedicalFieldType::Select->value)
            ->set('optionsText', 'OSDE')
            ->call('save')
            ->assertSet('showForm', true);
        $this->assertFalse(MedicalFormField::where('label', 'Obra social')->exists());

        // Reordenar: la segunda pregunta pasa a ser la primera.
        $allergies = MedicalFormField::where('label', '¿Tiene alergias?')->first();
        Livewire::test(MedicalFormIndex::class)->call('move', $allergies->id, -1);
        $this->assertSame(['¿Tiene alergias?', 'Grupo sanguíneo'], MedicalFormField::ordered()->pluck('label')->all());
    }

    public function test_member_signup_can_leave_the_medical_record_pending_or_fill_it(): void
    {
        $blood = $this->field(['label' => 'Grupo sanguíneo', 'type' => 'opcion', 'options' => ['0+', 'A+'], 'is_required' => true]);
        $diseases = $this->field(['label' => 'Enfermedades', 'type' => 'opciones', 'options' => ['Asma', 'Diabetes', 'Celiaquía']]);
        $this->actingAs($this->staff('secretaria'));
        $category = MemberCategory::where('name', 'Activo')->first();

        $signup = fn (string $document) => Livewire::test(MemberForm::class)
            ->set('first_name', 'Lucía')
            ->set('last_name', 'Fernández')
            ->set('document_number', $document)
            ->set('birth_date', today()->subYears(25)->toDateString())
            ->set('member_category_id', $category->id);

        // Sin datos médicos: el alta sigue y la ficha queda pendiente.
        $signup('30111222')->call('save')->assertHasNoErrors();
        $this->assertNull(Member::where('document_number', '30111222')->first()->medicalRecord);

        // Ficha a medio completar: se exigen los obligatorios.
        $signup('30111333')
            ->set("medical.{$diseases->id}", ['Asma'])
            ->call('save')
            ->assertHasErrors(["medical.{$blood->id}" => 'required']);

        $signup('30111444')
            ->set("medical.{$blood->id}", 'A+')
            ->set("medical.{$diseases->id}", ['Asma', 'Celiaquía'])
            ->call('save')
            ->assertHasNoErrors();

        $record = Member::where('document_number', '30111444')->first()->medicalRecord;
        $this->assertSame('A+', $record->answer($blood));
        $this->assertSame(['Asma', 'Celiaquía'], $record->answer($diseases));
    }

    public function test_medical_record_is_edited_after_signup_from_the_member_page(): void
    {
        $blood = $this->field(['label' => 'Grupo sanguíneo', 'type' => 'opcion', 'options' => ['0+', 'A+'], 'is_required' => true]);
        $medication = $this->field(['label' => 'Medicación', 'type' => 'texto_largo']);
        $member = $this->activeMember();
        $this->actingAs($this->staff('secretaria'));

        $this->get(route('admin.members.show', [$member, 'tab' => 'ficha']))->assertOk()->assertSee('Sin completar');

        Livewire::test(MedicalRecordTab::class, ['member' => $member])
            ->call('edit')
            ->call('save')
            ->assertHasErrors(["medical.{$blood->id}" => 'required'])
            ->set("medical.{$blood->id}", '0+')
            ->set("medical.{$medication->id}", '  Ibuprofeno  ')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editing', false)
            ->assertSee('Completa')
            ->assertSee('Ibuprofeno');

        $record = $member->fresh()->medicalRecord;
        $this->assertSame('Ibuprofeno', $record->answer($medication));
        $this->assertNotNull($record->updated_by);

        // Vaciar un campo opcional borra la respuesta.
        Livewire::test(MedicalRecordTab::class, ['member' => $member->fresh()])
            ->call('edit')
            ->set("medical.{$medication->id}", '')
            ->call('save');
        $this->assertNull($member->fresh()->medicalRecord->answer($medication));
    }

    public function test_answered_fields_are_deactivated_instead_of_deleted(): void
    {
        $allergies = $this->field(['label' => 'Alergias']);
        $unused = $this->field(['label' => 'Sin usar']);
        $member = $this->activeMember();
        app(MedicalRecordService::class)->save($member, [$allergies->id => 'Penicilina']);
        $this->actingAs($this->staff());

        Livewire::test(MedicalFormIndex::class)
            ->call('delete', $allergies->id)
            ->call('delete', $unused->id)
            ->call('edit', $allergies->id)
            ->set('type', MedicalFieldType::Number->value)
            ->call('save')
            ->assertSet('showForm', true);

        $this->assertNotNull($allergies->fresh());
        $this->assertSame(MedicalFieldType::Text, $allergies->fresh()->type);
        $this->assertNull($unused->fresh());

        Livewire::test(MedicalFormIndex::class)->call('toggle', $allergies->id);
        $this->assertFalse($allergies->fresh()->is_active);

        // Ya no se pide, pero lo cargado se sigue viendo.
        Livewire::test(MedicalRecordTab::class, ['member' => $member])
            ->assertSee('Datos de campos que ya no se piden')
            ->assertSee('Penicilina');
    }

    public function test_medical_permissions(): void
    {
        $field = $this->field(['label' => 'Alergias']);
        $member = $this->activeMember();

        // El profesor ve la ficha pero no la modifica ni arma el modelo.
        $this->actingAs($this->staff('profesor'));
        $this->get(route('admin.members.show', [$member, 'tab' => 'ficha']))->assertOk()->assertSee('Ficha médica');
        $this->get(route('admin.medical-form'))->assertForbidden();
        Livewire::test(MedicalRecordTab::class, ['member' => $member])->call('edit')->assertForbidden();

        // Recepción no ve la pestaña.
        $this->actingAs($this->staff('recepcion'));
        $this->get(route('admin.members.show', [$member, 'tab' => 'ficha']))->assertOk()->assertDontSee('Ficha médica');

        $this->assertFalse(MedicalRecord::exists());
        $this->assertNotNull($field);
    }
}
