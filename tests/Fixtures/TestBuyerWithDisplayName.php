<?php

namespace Amzad\LaravelPurchasable\Tests\Fixtures;

use Amzad\LaravelPurchasable\Concerns\Buyer;
use Amzad\LaravelPurchasable\Contracts\Buyer as BuyerContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Same table as TestBuyer, but implements the optional
 * getBuyerDisplayName() method — used to test the "implemented" path
 * without changing TestBuyer's fallback-path coverage.
 *
 * @property string $name
 */
class TestBuyerWithDisplayName extends Model implements BuyerContract
{
    use Buyer;

    protected $table = 'test_buyers';

    protected $guarded = ['id'];

    public function getBuyerDisplayName(): string
    {
        return $this->name;
    }
}
