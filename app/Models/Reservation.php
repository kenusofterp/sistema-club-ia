<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable(['facility_id', 'member_id', 'date', 'start_time', 'end_time', 'status', 'amount', 'notes', 'created_by', 'cancelled_at', 'cancel_reason'])]
class Reservation extends Model
{
    use Auditable, BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'date' => 'date',
            'amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class)->withTrashed();
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function fee(): HasOne
    {
        return $this->hasOne(Fee::class);
    }

    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', ReservationStatus::Confirmed);
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->whereDate('date', '>=', today())->orderBy('date')->orderBy('start_time');
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d').' '.$this->start_time);
    }

    public function timeRange(): string
    {
        return substr($this->start_time, 0, 5).' a '.substr($this->end_time, 0, 5);
    }
}
