<?php

namespace Stepanenko3\PlanPay\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Stepanenko3\PlanPay\Payment;
use Stepanenko3\PlanPay\Subscription;
use Symfony\Component\HttpFoundation\Response;
use LogicException;
use Arr;

class WebhookController extends Controller
{
    /**
     * Handle a webhook call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook(Request $request, $name)
    {
        $paymentMethods = config('planpay.payment_methods');
        
        if (!isset($paymentMethods[$name])) {
            throw new LogicException('Not found payment method.');
        }

        $paymentClass = Arr::get($paymentMethods, $name);

        $payment = new $paymentClass();
        $payment->webhook($request);

        return $this->successMethod();
    }

    /**
     * Handle successful calls on the controller.
     *
     * @param  array  $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function successMethod($parameters = [])
    {
        return new Response('Webhook Handled', 200);
    }
}
