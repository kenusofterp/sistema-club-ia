<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'type', 'menu_label', 'title', 'subtitle', 'content', 'image_path', 'items', 'sort_order', 'is_active'])]
class SiteSection extends Model
{
    use Auditable, BelongsToOrganization;

    /** Tipos de sección que sabe renderizar la página institucional. */
    public const TYPES = [
        'about' => 'Texto con imagen (Quiénes somos)',
        'features' => 'Tarjetas de beneficios',
        'stats' => 'Cifras destacadas',
        'activities' => 'Listado de actividades',
        'plans' => 'Planes y precios (gimnasio)',
        'facilities' => 'Listado de instalaciones',
        'news' => 'Últimas noticias',
        'cta' => 'Llamado a la acción (Asociate)',
        'contact' => 'Contacto y mapa',
        'text' => 'Texto libre',
    ];

    /** Tipos que admiten una lista editable de ítems (título, texto, icono/valor). */
    public const TYPES_WITH_ITEMS = ['features', 'stats'];

    protected function casts(): array
    {
        return ['items' => 'array', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        return storage_url($this->image_path);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
