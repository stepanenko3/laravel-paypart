<?php

namespace Stepanenko3\PlanPay;

use Carbon\Carbon;
use \Illuminate\Http\Request;
use InvalidArgumentException;

class LiqPay
{
    const CURRENCY_EUR = 'EUR';
    const CURRENCY_USD = 'USD';
    const CURRENCY_UAH = 'UAH';
    const CURRENCY_RUB = 'RUB';
    const CURRENCY_RUR = 'RUR';

    protected $_supportedCurrencies = array(
        self::CURRENCY_EUR,
        self::CURRENCY_USD,
        self::CURRENCY_UAH,
        self::CURRENCY_RUB,
        self::CURRENCY_RUR,
    );

    protected $_api_url;
    protected $_checkout_url;

    protected $_public_key;
    protected $_private_key;
    protected $_server_response_code = null;

    public function __construct()
    {
        $public_key = config('planpay.liqpay.public_key');        
        $private_key = config('planpay.liqpay.private_key');

        if (empty($public_key)) {
            throw new InvalidArgumentException('public_key is empty');
        }

        if (empty($private_key)) {
            throw new InvalidArgumentException('private_key is empty');
        }

        $this->_api_url = config('planpay.liqpay.api_url');
        $this->_checkout_url = config('planpay.liqpay.checkout_url');

        $this->_public_key = $public_key;
        $this->_private_key = $private_key;
    }

    public function createForm($options)
    {
        $params = [
            'version' => '3',
            'action' => 'pay',
            'amount' => $options['amount'],
            'currency' => $options['currency'],
            
            'description' => $options['description'],
            'order_id' => $options['order_id'],

            'result_url' => 'http://education.localhost:8888/demo',
            'server_url' => 'https://f7ee52f7.ngrok.io',
            'language' => 'ru',
            'sandbox' => '1'
        ];

        $language = 'ru';
        
        if (isset($params['language']) && $params['language'] == 'en') {
            $language = 'en';
        }
        
        $params = $this->cnb_params($params);
        $data = $this->encode_params($params);
        $signature = $this->cnb_signature($params);
        

        return sprintf('
            <form method="POST" action="%s" accept-charset="utf-8">
                %s
                %s
                <button type="submit" class="inline-block w-full px-4 py-3 mb-4 text-white rounded-lg bg-green-500 hover:bg-green-600">LiqPay</button>
            </form>
            ',
            $this->_checkout_url,
            sprintf('<input type="hidden" name="%s" value="%s" />', 'data', $data),
            sprintf('<input type="hidden" name="%s" value="%s" />', 'signature', $signature),
            $language
        );
    }

    public function webhook($request)
    {
        $data = $request->input('data');

        $private_key = $this->_private_key;
        $signature = $this->str_to_sign($private_key . $data . $private_key);

        if ($request->input('signature') !== $signature) {
            throw new InvalidArgumentException('invalid signature');
        }

        $params = $this->decode_params($data);

        $payment = Payment::where('id', $params->order_id)->first();

        if (!$payment) {
            $payment = new Payment([
                'id' => $params->order_id,
                'status' => Payment::STATUS_INCOMPLETE,
            ]);
        }
        
        if ($payment->status === Payment::STATUS_COMPLETE) {
            throw new InvalidArgumentException('payment alredy completed');
        }

        if (in_array($params->status, ['sandbox', 'success'])) {
            $payment->status = Payment::STATUS_COMPLETE;
        } else {
            $payment->status = Payment::STATUS_ERROR;
        }

        $payment->type = 'liqpay';
        $payment->callback = json_encode($params);
        $payment->amount = $params->amount;
        $payment->save();

        $paymentable = $payment->paymentable;
        if ($paymentable instanceof Subscription) {
            $paymentable->ends_at = $paymentable->endsFromAmountForPayment($params->amount);
            $paymentable->status = Subscription::STATUS_ACTIVE;
            $paymentable->save();
        }

        return true;
    }

    public function cnb_form_raw($params)
    {
        $params = $this->cnb_params($params);
        
        return array(
            'url' => $this->_checkout_url,
            'data' => $this->encode_params($params),
            'signature' => $this->cnb_signature($params)
        );
    }
    
    public function cnb_signature($params)
    {
        $params = $this->cnb_params($params);
        $private_key = $this->_private_key;
        $json = $this->encode_params($params);
        $signature = $this->str_to_sign($private_key . $json . $private_key);
        return $signature;
    }

    private function cnb_params($params)
    {
        $params['public_key'] = $this->_public_key;
        if (!isset($params['version'])) {
            throw new InvalidArgumentException('version is null');
        }
        if (!isset($params['amount'])) {
            throw new InvalidArgumentException('amount is null');
        }
        if (!isset($params['currency'])) {
            throw new InvalidArgumentException('currency is null');
        }
        if (!in_array($params['currency'], $this->_supportedCurrencies)) {
            throw new InvalidArgumentException('currency is not supported');
        }
        if ($params['currency'] == self::CURRENCY_RUR) {
            $params['currency'] = self::CURRENCY_RUB;
        }
        if (!isset($params['description'])) {
            throw new InvalidArgumentException('description is null');
        }
        return $params;
    }

    private function encode_params($params)
    {
        return base64_encode(json_encode($params));
    }
    
    public function decode_params($params)
    {
        return json_decode(base64_decode($params));
    }
    
    public function str_to_sign($str)
    {
        $signature = base64_encode(sha1($str, 1));
        return $signature;
    }
}
