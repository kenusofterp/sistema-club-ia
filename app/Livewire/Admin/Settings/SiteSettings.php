<?php

namespace App\Livewire\Admin\Settings;

class SiteSettings extends SettingsForm
{
    protected function groups(): array
    {
        return ['site', 'contact', 'social', 'seo', 'pwa'];
    }

    protected function permission(): string
    {
        return 'sitio.gestionar';
    }

    protected function pageTitle(): string
    {
        return 'Identidad y contacto';
    }
}
