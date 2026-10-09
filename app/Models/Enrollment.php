<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'activity_id', 'status', 'start_date', 'end_date', 'fee_amount', 'scholarship_percent', 'notes'])]
class Enrollment extends Model
{
    use Auditable, BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'fee_amount' => 'decimal:2',
            'scholarship_percent' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class)->withTrashed();
    }
}
