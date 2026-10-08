<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Control de acceso para molinetes / lectores QR. Requiere un usuario con permiso "acceso.registrar". */
class AccessController extends Controller
{
    public function check(Request $request, AccessService $access): JsonResponse
    {
        abort_unless($request->user()->can('acceso.registrar') && $request->user()->tokenCan('*'), 403);

        $data = $request->validate(['code' => 'required|string|max:200']);
        $member = $access->findMember($data['code']);

        if (! $member) {
            return response()->json(['result' => 'no_encontrado', 'message' => 'Socio no encontrado.'], 404);
        }

        $log = $access->register($member, $request->user());

        return response()->json([
            'result' => $log->result->value,
            'reason' => $log->reason,
            'member' => [
                'name' => $member->fullName(),
                'member_number' => $member->member_number,
                'category' => $member->category->name,
                'photo_url' => $member->photoUrl(),
            ],
        ]);
    }
}
