<?php

namespace Amzad\LaravelPurchasable\Contracts;

interface Purchasable
{
    public function getPurchasablePrice(): string|int|float;
}
