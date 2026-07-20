<?php

use Amzad\LaravelPurchasable\Actions\RecordTransaction;
use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\PendingState;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestBuyer;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestPurchasable;

it('proxies price() to getPurchasablePrice()', function () {
    $purchasable = TestPurchasable::create(['price' => '49.990']);

    expect($purchasable->price())->toBe('49.990');
});

it('falls back to the configured default currency when the host does not define one', function () {
    config()->set('purchasable.default_currency', 'KWD');

    $purchasable = TestPurchasable::create(['price' => '10.000']);

    expect($purchasable->currency())->toBe('KWD');
});

it('creates an order via purchase()', function () {
    $buyer = TestBuyer::create();
    $purchasable = TestPurchasable::create(['price' => '25.500']);

    $order = $purchasable->purchase($buyer, ['quantity' => 2]);

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->buyer_type)->toBe($buyer->getMorphClass())
        ->and($order->buyer_id)->toBe($buyer->getKey())
        ->and($order->purchasable_type)->toBe($purchasable->getMorphClass())
        ->and($order->purchasable_id)->toBe($purchasable->getKey())
        ->and($order->quantity)->toBe(2)
        ->and($order->amount)->toBe('51.000')
        ->and($order->status->equals(PendingState::class))->toBeTrue();

    expect($purchasable->orders()->count())->toBe(1);
});

it('reports isPurchasedBy() correctly before and after payment', function () {
    $buyer = TestBuyer::create();
    $purchasable = TestPurchasable::create(['price' => '10.000']);

    $order = $purchasable->purchase($buyer);

    expect($purchasable->isPurchasedBy($buyer))->toBeFalse();

    app(RecordTransaction::class)->execute(
        order: $order,
        type: TransactionType::Charge,
        gateway: 'manual',
        amount: $order->amount,
        status: TransactionStatus::Success,
    );

    expect($purchasable->isPurchasedBy($buyer))->toBeTrue();
});
