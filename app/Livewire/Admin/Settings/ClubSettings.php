<?php

namespace App\Livewire\Admin\Settings;

class ClubSettings extends SettingsForm
{
    protected function groups(): array
    {
        return ['club', 'gym', 'lessons'];
    }

    protected function permission(): string
    {
        return 'configuracion.gestionar';
    }

    protected function pageTitle(): string
    {
        return 'Configuración del club';
    }
}
