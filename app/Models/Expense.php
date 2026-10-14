<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Gasto de la entidad (alquiler del salón, servicios, sueldos…). */
#[Fillable(['expense_date', 'category', 'description', 'supplier', 'facility_id', 'period', 'amount', 'method', 'reference', 'attachment_path', 'created_by'])]
class Expense extends Model
{
    use Auditable, BelongsToOrganization, SoftDeletes;

    public const RENT = 'Alquiler';

    /** Categorías sugeridas; se puede escribir cualquier otra. */
    public const CATEGORIES = [
        self::RENT,
        'Servicios (luz, agua, gas, internet)',
        'Sueldos y honorarios',
        'Mantenimiento y limpieza',
        'Equipamiento',
        'Insumos',
        'Impuestos y seguros',
        'Publicidad',
        'Otros',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'period' => 'date',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function scopeBetween(Builder $query, $from, $to): void
    {
        $query->whereDate('expense_date', '>=', $from)->whereDate('expense_date', '<=', $to);
    }

    /** Categorías sugeridas más las que ya se usaron en la entidad. */
    public static function categoryOptions(): array
    {
        return collect(self::CATEGORIES)
            ->merge(static::query()->distinct()->orderBy('category')->pluck('category'))
            ->unique(fn (string $c) => mb_strtolower($c))
            ->values()
            ->all();
    }

    public function periodLabel(): ?string
    {
        return $this->period ? ucfirst($this->period->translatedFormat('F Y')) : null;
    }

    public function attachmentUrl(): ?string
    {
        return storage_url($this->attachment_path);
    }
}
