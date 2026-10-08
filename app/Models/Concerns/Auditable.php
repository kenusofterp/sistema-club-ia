<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Registra en la auditoría los cambios de los atributos "fillable" del modelo.
 * Los modelos pueden definir $auditExcept para excluir atributos sensibles.
 */
trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(property_exists($this, 'auditExcept') ? $this->auditExcept : [])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName($this->getTable());
    }
}
