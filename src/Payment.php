<?php

namespace Stepanenko3\PlanPay;

use Carbon\Carbon;
use LogicException;
use Illuminate\Database\Eloquent\Model;
use Arr;

class Payment extends Model
{
    const STATUS_INCOMPLETE = 'incomplete';
    const STATUS_COMPLETE = 'complete';
    const STATUS_CANCEL = 'cancel';
    const STATUS_ERROR = 'error';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at', 'updated_at',
    ];


    public function paymentable()
    {
        return $this->morphTo();
    }

    public function getForm($paymentMethod)
    {
        if (!$this->incomplete()) {
            throw new LogicException('Payment is completed');
        }

        $paymentClass = Arr::get(config('planpay.payment_methods'), $paymentMethod ?: config('planpay.payment_method'));

        $payment = new $paymentClass();
        $form = $payment->createForm([
            'amount' => $this->amount,
            'currency' => $this->currency,
            'description' => str_ireplace(':payment', $this->id, $this->description),
            'order_id' => $this->id,
        ]);

        return $form;
    }

    public function incomplete()
    {
        return $this->status !== self::STATUS_COMPLETE;
    }

    public function scopeIncomplete($query)
    {
        $query->where('status', '!=', self::STATUS_COMPLETE);
    }

    public function isComplete()
    {
        return $this->status === self::STATUS_COMPLETE;
    }

    public function scopeСomplete($query)
    {
        $query->where('status', self::STATUS_COMPLETE);
    }

    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCEL;
    }

    public function scopeCancelled($query)
    {
        $query->where('status', self::STATUS_CANCEL);
    }

    public function isError()
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function scopeError($query)
    {
        $query->where('status', self::STATUS_ERROR);
    }


    /**
     * Get the total amount that will be paid.
     *
     * @return string
     */
    public function amount()
    {
        return PlanPay::formatAmount($this->amount, $this->currency);
    }
}
