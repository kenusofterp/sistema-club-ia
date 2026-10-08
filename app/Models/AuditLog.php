<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Spatie\Activitylog\Models\Activity;

/** Registro de auditoría, separado por entidad. */
class AuditLog extends Activity
{
    use BelongsToOrganization;
}
