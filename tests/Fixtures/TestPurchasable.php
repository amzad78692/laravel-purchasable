<?php

namespace Amzad\LaravelPurchasable\Tests\Fixtures;

use Amzad\LaravelPurchasable\Concerns\Purchasable;
use Amzad\LaravelPurchasable\Contracts\Purchasable as PurchasableContract;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $price
 */
class TestPurchasable extends Model implements PurchasableContract
{
    use Purchasable;

    protected $table = 'test_purchasables';

    protected $guarded = ['id'];

    public function getPurchasablePrice(): string|int|float
    {
        return $this->price;
    }
}
