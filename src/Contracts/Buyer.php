<?php

namespace Amzad\LaravelPurchasable\Contracts;

use Amzad\LaravelPurchasable\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Buyer
{
    public function orders(): MorphMany;

    public function hasPurchased(Model $purchasable): bool;

    public function hasPendingOrderFor(Model $purchasable): bool;

    public function purchase(Model&Purchasable $purchasable, array $options = []): Order;

    public function purchaseHistory(): MorphMany;
}
