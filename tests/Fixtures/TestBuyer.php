<?php

namespace Amzad\LaravelPurchasable\Tests\Fixtures;

use Amzad\LaravelPurchasable\Concerns\Buyer;
use Amzad\LaravelPurchasable\Contracts\Buyer as BuyerContract;
use Illuminate\Database\Eloquent\Model;

class TestBuyer extends Model implements BuyerContract
{
    use Buyer;

    protected $table = 'test_buyers';

    protected $guarded = ['id'];
}
