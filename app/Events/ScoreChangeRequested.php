<?php

namespace App\Events;

use App\Models\ScoreChangeRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Llegó un pedido de modificación de nota (lo ven quienes pueden autorizarlo). */
class ScoreChangeRequested implements ShouldBroadcastNow
{
    public function __construct(public ScoreChangeRequest $request) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('competition.'.$this->request->competition_id.'.staff')];
    }

    /** @return array<string, int> */
    public function broadcastWith(): array
    {
        return ['request_id' => $this->request->id];
    }
}
