<?php

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestBuyer;

it('sums paid revenue within a date range via revenueBetween()', function () {
    Order::factory()->paid()->create(['amount' => '10.000', 'paid_at' => now()->subDays(5)]);
    Order::factory()->paid()->create(['amount' => '15.000', 'paid_at' => now()->subDays(3)]);
    Order::factory()->paid()->create(['amount' => '99.000', 'paid_at' => now()->subDays(20)]); // out of range
    Order::factory()->pending()->create(['amount' => '50.000']); // not paid, excluded

    $revenue = Order::revenueBetween(now()->subDays(7), now());

    expect($revenue)->toBe('25.000');
});

it('counts orders grouped by status via countsByStatus()', function () {
    Order::factory()->pending()->create();
    Order::factory()->pending()->create();
    Order::factory()->paid()->create();

    $counts = Order::countsByStatus();

    expect($counts['pending'])->toBe(2)
        ->and($counts['paid'])->toBe(1);
});

it('returns paid orders newest-first via purchaseHistory()', function () {
    $buyer = TestBuyer::create();

    $older = Order::factory()->paid()->create(['buyer_type' => $buyer->getMorphClass(), 'buyer_id' => $buyer->getKey(), 'paid_at' => now()->subDays(2)]);
    $newer = Order::factory()->paid()->create(['buyer_type' => $buyer->getMorphClass(), 'buyer_id' => $buyer->getKey(), 'paid_at' => now()->subDay()]);
    Order::factory()->pending()->create(['buyer_type' => $buyer->getMorphClass(), 'buyer_id' => $buyer->getKey()]);

    $history = $buyer->purchaseHistory()->get();

    expect($history)->toHaveCount(2)
        ->and($history->first()->is($newer))->toBeTrue()
        ->and($history->last()->is($older))->toBeTrue();
});
