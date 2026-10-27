<?php

namespace App\Events;

use App\Models\Evaluation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Los 3 jueces confirmaron: se publicó el puntaje (pantalla del puesto y ranking general). */
class EvaluationPublished implements ShouldBroadcastNow
{
    public function __construct(public Evaluation $evaluation) {}

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
        return [
            'evaluation_id' => $this->evaluation->id,
            'score' => (string) $this->evaluation->score,
            'totals' => $this->evaluation->sheets->mapWithKeys(fn ($sheet) => [$sheet->seat => (string) $sheet->total])->all(),
        ];
    }
}
