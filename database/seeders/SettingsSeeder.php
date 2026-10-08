<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\SettingsCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingsCatalog::definitions() as $index => $definition) {
            $setting = Setting::firstOrNew(['key' => $definition['key']]);

            $setting->fill([
                'group' => $definition['group'],
                'type' => $definition['type'],
                'label' => $definition['label'],
                'help' => $definition['help'] ?? null,
                'sort_order' => $index,
            ]);

            if (! $setting->exists) {
                $value = $definition['value'];

                // Imágenes iniciales (logo de la marca): se copian de public/ al disco público.
                if (isset($definition['source'])) {
                    $source = public_path($definition['source']);
                    if (is_file($source)) {
                        Storage::disk('public')->put($value, file_get_contents($source));
                    } else {
                        $value = null;
                    }
                }
                $setting->value = match (true) {
                    is_bool($value) => $value ? '1' : '0',
                    $value === null => null,
                    default => (string) $value,
                };
            }

            $setting->save();
        }
    }
}
