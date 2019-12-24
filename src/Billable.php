<?php

namespace Stepanenko3\PlanPay;

use Exception;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\DB;

trait Billable
{
    private $tmp_subscriptions = [];
    
    /**
     * Begin creating a new subscription.
     *
     * @param  string  $subscription
     * @param  string  $plan
     * @return \Stepanenko3\PlanPay\SubscriptionBuilder
     */
    public function newSubscription($subscription, $plan)
    {
        return new SubscriptionBuilder($this, $subscription, $plan);
    }

    /**
     * Determine if model is on trial.
     *
     * @param  string  $subscription
     * @param  string|null  $plan
     * @return bool
     */
    public function onTrial($subscription = 'default', $plan = null)
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($subscription);

        if (is_null($plan)) {
            return $subscription && $subscription->onTrial();
        }

        return $subscription && $subscription->onTrial() &&
                $subscription->plan === $plan;
    }

    /**
     * Determine if model has a given subscription.
     *
     * @param  string  $subscription
     * @param  string|null  $plan
     * @return bool
     */
    public function subscribed($subscription = 'default', $plan = null)
    {
        $subscription = $this->subscription($subscription);

        if (is_null($subscription)) {
            return false;
        }

        if (is_null($plan)) {
            return $subscription->valid();
        }

        return $subscription->valid() && $subscription->plan === $plan;
    }

    public function scopeSubscribed($query, $subscriptions = ['default'])
    {
        $query->whereHas('subscriptions', function($q) use($subscriptions) {
            $q->valid()->whereIn('subscription', (array) $subscriptions);
        });
    }

    public function scopeOrderBySubscribed($query)
    {
        $t = $this->getTable();

        $sorts = SubscriptionItem::select('order', 'name')
            ->get()
            ->map(function($item) {
                if (!$item->order) return '';
                return 'when subscriptions.subscription = \'' . $item->name . '\' then ' . $item->order;
            })
            ->push('else 0')
            ->join(' ');

        $query->leftJoin('subscriptions', function ($join) use($t) {
            $join->on($t . '.id', '=', 'subscriptions.subscriptionable_id')
                ->where('subscriptionable_type', '=', get_class($this));
        })
        ->addSelect(['subscriptions.subscription'])
        ->orderByRaw('case ' . $sorts . ' end DESC');
    }

    /**
     * Get a subscription instance by name.
     *
     * @param  string  $subscription
     * @return \Stepanenko3\PlanPay\Subscription|null
     */
    public function subscription($subscription = 'default')
    {
        if (!isset($this->tmp_subscriptions[$subscription])) {
            $query = $this->subscriptions ?: $this->subscriptions();

            $this->tmp_subscriptions[$subscription] = $query->where('subscription', $subscription)->first();
        }

        return $this->tmp_subscriptions[$subscription];
    }

    public function subscriptionsList($subscriptions = 'default')
    {
        $subscriptionsList = [];
        $notExists = [];

        foreach((array) $subscriptions as $subscription) {
            if (!isset($this->tmp_subscriptions[$subscription])) {
                $notExists[] = $subscription;
            } else {
                $subscriptionsList[] = $this->tmp_subscriptions[$subscription];
            }
        }

        $query = $this->subscriptions 
            ? $this->subscriptions->whereIn('subscription', $notExists)->all()
            : $this->subscriptions()->whereIn('subscription', $notExists)->get();

        $subscriptionsList = collect($query)->merge($subscriptionsList);

        dd($subscriptionsList);
        return $subscriptionsList;
    }

    /**
     * Get all of the subscriptions for model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->morphMany(Subscription::class, 'subscriptionable')->orderBy('created_at', 'desc');
    }

    /**
     * Determine if the customer's subscription has an incomplete payment.
     *
     * @param  string  $subscription
     * @return bool
     */
    public function hasIncompletePayment($subscription = 'default')
    {
        if ($subscription = $this->subscription($subscription)) {
            return $subscription->hasIncompletePayment();
        }

        return false;
    }

    public function latestPayment($subscription = 'default')
    {
        if ($subscription = $this->subscription($subscription)) {
            return $subscription->latestPayment();
        }

        return false;
    }

    /**
     * Determine if model is actively subscribed to one of the given plans.
     *
     * @param  array|string  $plans
     * @param  string  $subscription
     * @return bool
     */
    public function subscribedToPlan($plans, $subscription = 'default')
    {
        $subscription = $this->subscription($subscription);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $plans as $plan) {
            if ($subscription->plan === $plan) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the entity is on the given plan.
     *
     * @param  string  $plan
     * @return bool
     */
    public function onPlan($plan)
    {
        return !is_null($this->subscriptions->first(function ($value) use ($plan) {
            return $value->plan === $plan && $value->valid();
        }));
    }


    /**
     * Get supported currency used by the entity.
     *
     * @return string
     */
    public function preferredCurrency()
    {
        return config('planpay.currency');
    }







    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int  $amount
     * @param  string  $paymentMethod
     * @param  array  $options
     * @return \Stepanenko3\PlanPay\Payment
     */
    public function charge($amount, $paymentMethod, array $options = [])
    {
        $options = array_merge([
            'currency' => $this->preferredCurrency(),
        ], $options);

        $options['amount'] = $amount;
        $options['payment_method'] = $paymentMethod;

        $payment = Payment::create($options);

        $payment->validate();

        return $payment;
    }

    /**
     * Add an invoice item to the customer's upcoming invoice.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $options
     */
    public function tab($description, $amount, array $options = [])
    {
        $this->assertCustomerExists();

        $options = array_merge([
            'customer' => $this->id,
            'amount' => $amount,
            'currency' => $this->preferredCurrency(),
            'description' => $description,
        ], $options);

        return InvoiceItem::create($options, $this->ptions());
    }


    /**
     * Apply a coupon to the billable entity.
     *
     * @param  string  $coupon
     * @return void
     */
    public function applyCoupon($coupon)
    {
        $this->assertCustomerExists();

        $customer = $this->asCustomer();

        $customer->coupon = $coupon;

        $customer->save();
    }

}
