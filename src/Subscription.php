<?php

namespace Stepanenko3\PlanPay;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Arr;

class Subscription extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_CANCEL = 'cancelled';

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
        'trial_ends_at', 'ends_at',
        'created_at', 'updated_at',
    ];

    /**
     * Indicates if the plan change should be prorated.
     *
     * @var bool
     */
    protected $prorate = true;

    /**
     * The date on which the billing cycle should be anchored.
     *
     * @var string|null
     */
    protected $billingCycleAnchor = null;


    /**
     * Get the model related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->morphTo();
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'paymentable')->orderBy('created_at', 'desc');
    }

    public function item()
    {
        return $this->belongsTo(SubscriptionItem::class, 'subscription', 'name');
    }

    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     *
     * @return bool
     */
    public function valid()
    {
        return $this->onTrial() || $this->onGracePeriod();
    }

    public function scopeValid($query)
    {
        $query
            ->where(function($q) {
                $q->onTrial();
            })
            ->orWhere(function($q) {
                $q->onGracePeriod();
            });
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function cancelled()
    {
        return $this->status === self::STATUS_CANCEL;
    }

    /**
     * Filter query by cancelled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeCancelled($query)
    {
        $query->where('status', self::STATUS_CANCEL);
    }

    /**
     * Filter query by not cancelled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotCancelled($query)
    {
        $query->where('status', '!=', self::STATUS_CANCEL);
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     *
     * @return bool
     */
    public function ended()
    {
        return !$this->onGracePeriod() && !$this->onTrial();
    }

    /**
     * Filter query by ended.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeEnded($query)
    {
        $query->notOnGracePeriod()->notOnTrial();
    }

    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Filter query by on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnTrial($query)
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Filter query by not on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotOnTrial($query)
    {
        $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', Carbon::now());
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod()
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Filter query by on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnGracePeriod($query)
    {
        $query->whereNotNull('ends_at')->where('ends_at', '>', Carbon::now());
    }

    /**
     * Filter query by not on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotOnGracePeriod($query)
    {
        $query->whereNull('ends_at')->orWhere('ends_at', '<=', Carbon::now());
    }

    /**
     * Force the trial to end immediately.
     *
     * This method must be combined with swap, resume, etc.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->trial_ends_at = null;

        return $this;
    }

    /**
     * Swap the subscription to a new plan.
     *
     * @param  string  $plan
     * @param  array  $options
     * @return $this
     * 
     */
    public function swap($subscription)
    {
        $item = $this->item;
        
        if ($item->name == $subscription) {
            throw new LogicException('Unable to swap. Already subscribet to' . $subscription);
        }

        $newItem = SubscriptionItem::where('name', $subscription)->firstOrFail();
        $endsAt = $this->endsAt();
        $newEndsAt = Carbon::now();
        
        if ($endsAt->isFuture()) {
            $monthMinutes = 43800;
            $diffMinutes = $endsAt->diffInMinutes();
    
            $minutes = $item->price / $monthMinutes * $diffMinutes / ($newItem->price / $monthMinutes);
            $newEndsAt->addMinutes($minutes + 1);
        }

        $this->fill([
            'subscription' => $subscription,
            'ends_at' => $newEndsAt,
        ])->save();

        return $this;
    }

    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @return $this
     */
    public function cancel()
    {
        $this->fill([
            'status' => self::STATUS_CANCEL,
        ])->save();

        return $this;
    }

    /**
     * Cancel the subscription immediately.
     *
     * @return $this
     */
    public function cancelNow()
    {
        $this->skipTrial()->fill([
            'status' => self::STATUS_CANCEL,
            'ends_at' => $this->ends_at->isFuture() ? Carbon::now() : $this->ends_at,
        ])->save();

        return $this;
    }

    /**
     * Resume the cancelled subscription.
     *
     * @return $this
     * @throws \LogicException
     */
    public function resume()
    {
        if (!$this->onGracePeriod() && !$this->onTrial()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }
        
        $this->fill([
            'status' => self::STATUS_ACTIVE,
        ])->save();

        return $this;
    }

    /**
     * Determine if the subscription has an incomplete payment.
     *
     * @return bool
     */
    public function hasIncompletePayment()
    {
        return $this->payments()->incomplete()->count() > 0;
    }

    /**
     * Get the latest payment for a Subscription.
     *
     * @return \Stepanenko3\PlanPay\Payment|null
     */
    public function latestPayment()
    {
        return $this->payments()->first();
    }

    public function incompletePayment()
    {
        return $this->payments()->incomplete()->first();
    }

    public function newOrUpdatePayment($months = 1)
    {
        $amount = $this->paymentAmount($months);

        $dataPayment = [
            'amount' => $amount,
            'currency' => 'UAH',
            'description' => trans('payment_description', [
                'plan' => $this->item->title,
                'period' => trans_choice('payment_description_months', $months, ['value' => $months]),
            ]),
        ];

        $payment = $this->incompletePayment();
        
        if ($payment) {
            $payment->fill($dataPayment)->save();
        } else {
            $payment = $this->payments()->create(array_merge([
                'status' => Payment::STATUS_INCOMPLETE,
            ], $dataPayment));
        }

        return $payment;
    }

    public function paymentAmount($months = 1)
    {
        $subItem = $this->item;

        $amount = $subItem->price * $months;

        $data = json_decode($subItem->data, true);

        if (isset($data['discount']) && count($data['discount']) > 0) {
            $item = collect($data['discount'])
                ->where('months', '<=', $months)
                ->sortByDesc('months')
                ->first();

            if ($item) {
                $amount = $amount - ($amount / 100 * $item['discount']);
            }
        }

        return $amount;
    }

    public function daysFromAmount($amount = 0)
    {
        return round($this->minutesFromAmount($amount) / 1440);
    }

    public function minutesFromAmount($amount = 0)
    {
        $monthMinutes = 43800;
        $subItem = $this->item;

        $data = json_decode($subItem->data, true);
        $period = 0;

        if (isset($data['discount']) && count($data['discount']) > 0) {
            $discounts = collect($data['discount'])->map(function($item) use($subItem, $amount, $monthMinutes) {
                $discount = $subItem->price * $item['months'];
                $discount = $discount - ($discount / 100 * $item['discount']);
                $perMonth = $discount / $item['months'];

                return [
                    'fit' => $amount > $discount,
                    'period' => $amount / $perMonth * $monthMinutes,
                ];
            });

            $period = $discounts->where('fit', true)->pluck('period')->first();
        }

        if ($period) {
            $minutes = $period;
        } else {
            $minutes = $amount / $subItem->price * $monthMinutes;
        }

        return round($minutes);
    }

    public function endsAt()
    {
        if ($this->onTrial()) {
            $endsAt = $this->trial_ends_at;
        } else {
            $endsAt = $this->ends_at;
        }

        return $endsAt;
    }

    public function endsFromAmountForPayment($amount)
    {
        $endsAt = $this->endsAt();
        if (!$endsAt || !$endsAt->isFuture()) $endsAt = Carbon::now();
        $endsAt->addMinutes($this->minutesFromAmount($amount));
        return $endsAt;
    }











    /**
     * Sync the tax percentage of the user to the subscription.
     *
     * @return void
     */
    public function syncTaxPercentage()
    {
        $subscription = $this->asSubscription();

        $subscription->tax_percent = $this->user->taxPercentage();

        $subscription->save();
    }

    /**
     * Indicate that the plan change should not be prorated.
     *
     * @return $this
     */
    public function noProrate()
    {
        $this->prorate = false;

        return $this;
    }

    /**
     * Change the billing cycle anchor on a plan change.
     *
     * @param  \DateTimeInterface|int|string  $date
     * @return $this
     */
    public function anchorBillingCycleOn($date = 'now')
    {
        if ($date instanceof DateTimeInterface) {
            $date = $date->getTimestamp();
        }

        $this->billingCycleAnchor = $date;

        return $this;
    }

    /**
     * Sync status of the subscription.
     *
     * @return void
     */
    public function syncStatus()
    {
        $subscription = $this->asSubscription();

        $this->payment_status = $subscription->status;

        $this->save();
    }
}
