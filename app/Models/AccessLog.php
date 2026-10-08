<?php

namespace App\Models;

use App\Enums\AccessResult;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'subscription_id', 'activity_id', 'result', 'reason', 'checked_by', 'checked_at'])]
class AccessLog extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['result' => AccessResult::class, 'checked_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class)->withTrashed();
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by')->withTrashed();
    }
}
