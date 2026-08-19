<?php

namespace Amzad\LaravelPurchasable\Tests\Fixtures;

use Amzad\LaravelPurchasable\Concerns\Purchasable;
use Amzad\LaravelPurchasable\Contracts\Purchasable as PurchasableContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Same table as TestPurchasable, but implements the optional
 * getPurchasableDisplayName() method — used to test the "implemented" path
 * without changing TestPurchasable's fallback-path coverage.
 *
 * @property string $price
 * @property string $name
 */
class TestPurchasableWithDisplayName extends Model implements PurchasableContract
{
    use Purchasable;

    protected $table = 'test_purchasables';

    protected $guarded = ['id'];

    public function getPurchasablePrice(): string|int|float
    {
        return $this->price;
    }

    public function getPurchasableDisplayName(): string
    {
        return $this->name;
    }
}
