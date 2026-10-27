<?php

namespace App\Events;

use App\Models\CompetitionStation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Cambió la participante actual del puesto (publicación, ausente, reorden de la cola). */
class StationAdvanced implements ShouldBroadcastNow
{
    public function __construct(public CompetitionStation $station) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('display.station.'.$this->station->display_token)];
    }

    /** @return array<string, int|null> */
    public function broadcastWith(): array
    {
        return ['current_evaluation_id' => $this->station->currentEvaluation()?->id];
    }
}
