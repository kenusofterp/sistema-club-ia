<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Recibo de pago imprimible (o "Guardar como PDF" desde el navegador). */
class ReceiptController extends Controller
{
    public function admin(Payment $payment): View
    {
        return $this->render($payment);
    }

    public function member(Request $request, Payment $payment): View
    {
        abort_unless($payment->member_id === $request->user()->currentMember()?->id, 403);

        return $this->render($payment);
    }

    private function render(Payment $payment): View
    {
        return view('receipt', ['payment' => $payment->load(['member.category', 'fees', 'receiver'])]);
    }
}
