<?php

namespace App\Services;

use App\Enums\MedicalFieldType;
use App\Exceptions\BusinessRuleException;
use App\Models\MedicalFormField;
use App\Models\MedicalRecord;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Ficha médica configurable: la entidad arma el modelo (campos) y cada socio guarda sus respuestas.
 * Las respuestas se guardan por id de campo, así el modelo puede cambiar sin perder lo ya cargado:
 * un campo con respuestas no se borra ni cambia de tipo, se desactiva.
 */
class MedicalRecordService
{
    /** @return Collection<int, MedicalFormField> */
    public function activeFields(): Collection
    {
        return MedicalFormField::active()->ordered()->get();
    }

    /**
     * Valores para el formulario: lo guardado del socio o vacío.
     *
     * @param  Collection<int, MedicalFormField>  $fields
     * @return array<int, mixed>
     */
    public function formValues(Collection $fields, ?Member $member = null): array
    {
        $record = $member?->medicalRecord;

        return $fields->mapWithKeys(fn (MedicalFormField $field) => [
            $field->id => $record?->answer($field) ?? ($field->type === MedicalFieldType::Checkboxes ? [] : ''),
        ])->all();
    }

    /**
     * @param  Collection<int, MedicalFormField>  $fields
     * @return array<string, mixed>
     */
    public function rules(Collection $fields, string $prefix = 'medical'): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $key = "{$prefix}.{$field->id}";
            $presence = $field->is_required ? 'required' : 'nullable';
            $options = $field->options ?? [];

            $rules[$key] = match ($field->type) {
                MedicalFieldType::Text => [$presence, 'string', 'max:255'],
                MedicalFieldType::Textarea => [$presence, 'string', 'max:2000'],
                MedicalFieldType::Number => [$presence, 'numeric', 'max:99999999'],
                MedicalFieldType::Date => [$presence, 'date'],
                MedicalFieldType::YesNo => [$presence, Rule::in(['si', 'no'])],
                MedicalFieldType::Select => [$presence, Rule::in($options)],
                MedicalFieldType::Checkboxes => [$presence, 'array', ...($field->is_required ? ['min:1'] : [])],
            };
            if ($field->type === MedicalFieldType::Checkboxes) {
                $rules["{$key}.*"] = [Rule::in($options)];
            }
        }

        return $rules;
    }

    /**
     * Nombres de los campos para los mensajes de validación.
     *
     * @param  Collection<int, MedicalFormField>  $fields
     * @return array<string, string>
     */
    public function attributes(Collection $fields, string $prefix = 'medical'): array
    {
        return $fields->flatMap(fn (MedicalFormField $field) => [
            "{$prefix}.{$field->id}" => $field->label,
            "{$prefix}.{$field->id}.*" => $field->label,
        ])->all();
    }

    /** @param  array<int, mixed>  $values */
    public function isBlank(array $values): bool
    {
        return collect($values)->every(fn ($value) => $value === null || $value === [] || (is_string($value) && trim($value) === ''));
    }

    /**
     * Guarda las respuestas del socio. Las respuestas de campos desactivados que no vienen en $values
     * se conservan. Con $onlyIfFilled no se crea una ficha vacía (alta de socio sin datos médicos).
     *
     * @param  array<int, mixed>  $values  id de campo => valor
     */
    public function save(Member $member, array $values, bool $onlyIfFilled = false): ?MedicalRecord
    {
        $record = $member->medicalRecord;
        if (! $record && $onlyIfFilled && $this->isBlank($values)) {
            return null;
        }

        $fields = MedicalFormField::whereKey(array_keys($values))->get()->keyBy('id');
        $answers = $record?->answers ?? [];
        foreach ($values as $id => $value) {
            $field = $fields->get((int) $id);
            if (! $field) {
                continue;
            }
            $value = $this->normalize($field, $value);
            if ($value === null) {
                unset($answers[$field->id]);
            } else {
                $answers[$field->id] = $value;
            }
        }

        $record = MedicalRecord::updateOrCreate(['member_id' => $member->id], [
            'answers' => $answers,
            'updated_by' => auth()->id(),
        ]);
        $member->setRelation('medicalRecord', $record);

        return $record;
    }

    /**
     * Campos obligatorios activos sin responder (ficha incompleta).
     *
     * @return Collection<int, MedicalFormField>
     */
    public function missingRequired(Member $member, ?Collection $fields = null): Collection
    {
        $record = $member->medicalRecord;

        return ($fields ?? $this->activeFields())
            ->filter(fn (MedicalFormField $field) => $field->is_required && $field->display($record?->answer($field)) === null)
            ->values();
    }

    /**
     * Crea o modifica un campo del modelo de ficha.
     *
     * @param  array{label: string, type: string, section?: ?string, help?: ?string, options?: array<int, string>, is_required?: bool, is_active?: bool}  $data
     */
    public function saveField(?MedicalFormField $field, array $data): MedicalFormField
    {
        $type = MedicalFieldType::from($data['type']);
        $options = null;
        if ($type->hasOptions()) {
            $options = collect($data['options'] ?? [])->map(fn ($o) => trim((string) $o))->filter()->unique()->values()->all();
            if (count($options) < 2) {
                throw new BusinessRuleException('Cargá al menos dos opciones, una por renglón.');
            }
        }

        if ($field && $field->type !== $type && $this->hasAnswers($field)) {
            throw new BusinessRuleException('Ya hay fichas con respuestas en este campo: no se puede cambiar el tipo. Creá un campo nuevo y desactivá este.');
        }

        $payload = [
            'label' => trim($data['label']),
            'type' => $type,
            'section' => trim((string) ($data['section'] ?? '')) ?: null,
            'help' => trim((string) ($data['help'] ?? '')) ?: null,
            'options' => $options,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($field) {
            $field->update($payload);

            return $field;
        }

        return MedicalFormField::create([...$payload, 'sort_order' => (int) MedicalFormField::max('sort_order') + 1]);
    }

    public function deleteField(MedicalFormField $field): void
    {
        if ($this->hasAnswers($field)) {
            throw new BusinessRuleException('Hay fichas con respuestas en este campo. Desactivalo para que no se pida más sin perder lo cargado.');
        }

        $field->delete();
    }

    /** Sube (-1) o baja (+1) un campo en el orden de la ficha. */
    public function move(MedicalFormField $field, int $direction): void
    {
        $fields = MedicalFormField::ordered()->get()->values();
        $from = $fields->search(fn (MedicalFormField $f) => $f->id === $field->id);
        $to = $from + ($direction < 0 ? -1 : 1);
        if ($from === false || ! isset($fields[$to])) {
            return;
        }

        $ordered = $fields->all();
        [$ordered[$from], $ordered[$to]] = [$ordered[$to], $ordered[$from]];

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $index => $f) {
                if ($f->sort_order !== $index + 1) {
                    $f->update(['sort_order' => $index + 1]);
                }
            }
        });
    }

    public function hasAnswers(MedicalFormField $field): bool
    {
        return MedicalRecord::whereRaw('jsonb_exists(answers, ?)', [(string) $field->id])->exists();
    }

    private function normalize(MedicalFormField $field, mixed $value): mixed
    {
        if ($field->type === MedicalFieldType::Checkboxes) {
            $selected = array_values(array_filter((array) $value, fn ($o) => is_string($o) && $o !== ''));

            return $selected ?: null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' || $value === null ? null : $value;
    }
}
