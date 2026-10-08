<?php

namespace App\Livewire\Admin\Settings;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Setting;
use App\Support\SettingsCatalog;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Formulario genérico de configuraciones agrupadas por pestañas.
 * Los valores se indexan por id de Setting (las claves con puntos no sirven para wire:model).
 */
#[Layout('layouts.admin')]
abstract class SettingsForm extends Component
{
    use InteractsWithUi, WithFileUploads;

    /** @var array<int, mixed> */
    public array $values = [];

    /** @var array<int, mixed> */
    public array $uploads = [];

    public string $tab = '';

    /** @return array<int, string> grupos que edita este formulario */
    abstract protected function groups(): array;

    abstract protected function permission(): string;

    public function mount(): void
    {
        $this->tab = $this->groups()[0];

        foreach ($this->settings() as $setting) {
            $this->values[$setting->id] = match ($setting->type) {
                'boolean' => (bool) $setting->value,
                default => (string) $setting->value,
            };
        }
    }

    protected function settings()
    {
        return Setting::whereIn('group', $this->groups())->orderBy('sort_order')->get();
    }

    public function save(): void
    {
        $this->authorize($this->permission());

        $settings = $this->settings();
        $rules = [];
        foreach ($settings as $s) {
            $rules["values.{$s->id}"] = match ($s->type) {
                'boolean' => 'boolean',
                'integer' => 'nullable|integer|min:0|max:100000',
                'decimal' => 'nullable|numeric|min:0|max:100000',
                'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'select' => ['required', Rule::in(array_keys(SettingsCatalog::options($s->key)))],
                'text' => 'nullable|string|max:5000',
                'image' => 'nullable',
                default => str_starts_with($s->key, 'social.') || $s->key === 'contact.map_embed_url'
                    ? 'nullable|url|max:500'
                    : (str_ends_with($s->key, '.email') ? 'nullable|email|max:150' : 'nullable|string|max:255'),
            };
            if ($s->type === 'image') {
                $rules["uploads.{$s->id}"] = 'nullable|image|max:4096';
            }
        }

        $attributes = $settings->mapWithKeys(fn ($s) => ["values.{$s->id}" => mb_strtolower($s->label), "uploads.{$s->id}" => mb_strtolower($s->label)])->all();
        $this->validate($rules, [], $attributes);

        foreach ($settings as $setting) {
            if ($setting->type === 'image') {
                if ($file = $this->uploads[$setting->id] ?? null) {
                    $setting->value = $file->store('settings', 'public');
                }
            } else {
                $value = $this->values[$setting->id] ?? null;
                $setting->value = match ($setting->type) {
                    'boolean' => $value ? '1' : '0',
                    default => $value === '' ? null : (string) $value,
                };
            }

            if ($setting->isDirty()) {
                $setting->save();
            }
        }

        $this->uploads = [];
        $this->notify('Configuración guardada.');
    }

    public function removeImage(int $id): void
    {
        $this->authorize($this->permission());
        $setting = Setting::whereIn('group', $this->groups())->findOrFail($id);
        $setting->update(['value' => null]);
        unset($this->uploads[$id]);
        $this->notify('Imagen eliminada.');
    }

    public function render()
    {
        return view('livewire.admin.settings.form', [
            'grouped' => $this->settings()->groupBy('group'),
            'groupLabels' => SettingsCatalog::GROUPS,
            'pageTitle' => $this->pageTitle(),
        ])->title($this->pageTitle());
    }

    abstract protected function pageTitle(): string;
}
