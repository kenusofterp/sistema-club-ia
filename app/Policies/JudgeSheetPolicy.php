<?php

namespace App\Policies;

use App\Models\Evaluation;
use App\Models\JudgeSheet;
use App\Models\StationJudge;
use App\Models\User;

/** Cada juez ve y carga solo su propia nota, y solo en el puesto que tiene asignado. */
class JudgeSheetPolicy
{
    /** Cargar (confirmar) la nota de una pasada: juez de ese puesto. */
    public function create(User $user, Evaluation $evaluation): bool
    {
        return $user->can('notas.cargar')
            && StationJudge::where('competition_station_id', $evaluation->competition_station_id)->where('user_id', $user->id)->exists();
    }

    public function view(User $user, JudgeSheet $sheet): bool
    {
        return $sheet->user_id === $user->id || $user->canAny(['competencias.gestionar', 'notas.autorizar']);
    }

    /** Pedir la modificación de una nota confirmada: el propio juez o un supervisor. */
    public function requestChange(User $user, JudgeSheet $sheet): bool
    {
        return ($sheet->user_id === $user->id && $user->can('notas.cargar')) || $user->can('notas.autorizar');
    }
}
