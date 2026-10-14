<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Carnet para imprimir (frente y dorso, tamaño tarjeta) con foto, datos y QR. */
class MemberCardController extends Controller
{
    /** Desde la ficha del socio (personal). */
    public function admin(Member $member): View
    {
        return $this->render($member);
    }

    /** El propio socio, desde el portal. */
    public function member(Request $request): View
    {
        $member = $request->user()->currentMember();
        abort_unless($member, 404);

        return $this->render($member);
    }

    private function render(Member $member): View
    {
        return view('member-card', [
            'member' => $member->load('category'),
            'qr' => $member->qrSvg(),
        ]);
    }
}
