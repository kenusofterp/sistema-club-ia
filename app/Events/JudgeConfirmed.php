<?php

namespace App\Events;

use App\Models\Evaluation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Un juez confirmó su nota (la pantalla del puesto muestra el ✓; la nota se ve al publicarse). */
class JudgeConfirmed implements ShouldBroadcastNow
{
    public function __construct(public Evaluation $evaluation, public int $seat) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('display.station.'.$this->evaluation->station->display_token)];
    }

    /** @return array<string, int> */
    public function broadcastWith(): array
    {
        return ['evaluation_id' => $this->evaluation->id, 'seat' => $this->seat];
    }
}
