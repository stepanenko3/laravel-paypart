<?php

namespace Stepanenko3\PlanPay;

use Carbon\Carbon;
use DateTimeInterface;

class SubscriptionBuilder
{
    /**
     * The model that is subscribing.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The name of the subscription.
     *
     * @var string
     */
    protected $name;

    /**
     * The name of the plan being subscribed to.
     *
     * @var string
     */
    protected $plan;

    protected $interval = 1;

    /**
     * The date and time the trial will expire.
     *
     * @var \Carbon\Carbon|\Carbon\CarbonInterface
     */
    protected $trialExpires;

    /**
     * Indicates that the trial should end immediately.
     *
     * @var bool
     */
    protected $skipTrial = false;

    /**
     * The coupon code being applied to the customer.
     *
     * @var string|null
     */
    protected $coupon;

    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $owner
     * @param  string  $name
     * @param  string  $plan
     * @return void
     */
    public function __construct($owner, $name, $plan)
    {
        $this->name = $name;
        $this->plan = $plan;
        $this->owner = $owner;
    }

    public function interval($interval)
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * Specify the number of days of the trial.
     *
     * @param  int  $trialDays
     * @return $this
     */
    public function trialDays($trialDays)
    {
        $this->trialExpires = Carbon::now()->addDays($trialDays);

        return $this;
    }

    /**
     * Specify the ending date of the trial.
     *
     * @param  \Carbon\Carbon|\Carbon\CarbonInterface  $trialUntil
     * @return $this
     */
    public function trialUntil($trialUntil)
    {
        $this->trialExpires = $trialUntil;

        return $this;
    }

    /**
     * Force the trial to end immediately.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->skipTrial = true;

        return $this;
    }

    /**
     * The coupon to apply to a new subscription.
     *
     * @param  string  $coupon
     * @return $this
     */
    public function withCoupon($coupon)
    {
        $this->coupon = $coupon;

        return $this;
    }

    public function create()
    {
        if ($this->skipTrial) {
            $trialEndsAt = null;
        } else {
            $trialEndsAt = $this->trialExpires;
        }

        $subscription = $this->owner->subscriptions()->where('subscription', $this->name)->first();

        // if ($subscription && ($subscription->plan !== $this->plan || $subscription->interval !== $this->interval)) {
        //     $subscription->swap($this->plan, $this->interval);
        // }

        if (!$subscription) {
            $subscription = $this->owner->subscriptions()->create([
                'user_id' => auth()->check() ? auth()->user()->id : 0,
                'subscription' => $this->name,
                'plan' => $this->plan,
                'interval' => $this->interval,
                'trial_ends_at' => $trialEndsAt,
                'ends_at' => null,
            ]);
        }

        $subscription->newPayment($subscription->interval);
        
        return $subscription;
    }









    /**
     * Get the tax percentage for the payload.
     *
     * @return int|float|null
     */
    protected function getTaxPercentageForPayload()
    {
        if ($taxPercentage = $this->owner->taxPercentage()) {
            return $taxPercentage;
        }
    }
}
