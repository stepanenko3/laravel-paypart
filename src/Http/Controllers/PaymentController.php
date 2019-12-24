<?php

namespace Stepanenko3\PlanPay\Http\Controllers;

use Illuminate\Routing\Controller;
use Stepanenko3\PlanPay\PlanPay;
use Stepanenko3\PlanPay\Payment;

class PaymentController extends Controller
{
    /**
     * Display the form to gather additional payment verification for the given payment.
     *
     * @param  string  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $payment = Payment::findOrFail($id);

        return view('planpay::payment', [
            'payment' => $payment,
            'redirect' => request('redirect'),
            'methods' => config('planpay.payment_methods'),
        ]);
    }
}
