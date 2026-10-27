<?php

namespace App\Events;

use App\Models\Evaluation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Se aprobó una modificación: cambió una nota publicada (pantalla del puesto y ranking general). */
class ScoreUpdated implements ShouldBroadcastNow
{
    public function __construct(public Evaluation $evaluation, public ?string $oldScore, public ?string $newScore) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new Channel('display.station.'.$this->evaluation->station->display_token),
            new Channel('display.competition.'.$this->evaluation->competition->display_token),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['evaluation_id' => $this->evaluation->id, 'old_score' => $this->oldScore, 'new_score' => $this->newScore];
    }
}
