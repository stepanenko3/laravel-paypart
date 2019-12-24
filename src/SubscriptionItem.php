<?php

namespace Stepanenko3\PlanPay;

use Illuminate\Database\Eloquent\Model;

class SubscriptionItem extends Model
{
    public $timestamps = false;
    
    public function discounts()
    {
        return $this->hasMany(SubscriptionDiscount::class, 'subscription_item_id');
    }
}
