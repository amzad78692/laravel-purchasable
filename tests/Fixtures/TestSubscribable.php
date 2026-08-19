<?php

namespace Amzad\LaravelPurchasable\Tests\Fixtures;

use Amzad\LaravelPurchasable\Concerns\Purchasable;
use Amzad\LaravelPurchasable\Contracts\Purchasable as PurchasableContract;
use Illuminate\Database\Eloquent\Model;

/**
 * A second purchasable model, distinct from TestPurchasable, used to
 * exercise multi-item orders that mix purchasable types.
 *
 * @property string $price
 */
class TestSubscribable extends Model implements PurchasableContract
{
    use Purchasable;

    protected $table = 'test_subscribables';

    protected $guarded = ['id'];

    public function getPurchasablePrice(): string|int|float
    {
        return $this->price;
    }
}
