<?php

use Amzad\LaravelPurchasable\Actions\RecordTransaction;
use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Exceptions\AlreadyPurchasedException;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestBuyer;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestPurchasable;

it('creates an order via purchase()', function () {
    $buyer = TestBuyer::create();
    $purchasable = TestPurchasable::create(['price' => '10.000']);

    $order = $buyer->purchase($purchasable);

    expect($order)->toBeInstanceOf(Order::class);
    expect($buyer->orders()->count())->toBe(1);
});

it('reports hasPurchased() and hasPendingOrderFor() correctly through the order lifecycle', function () {
    $buyer = TestBuyer::create();
    $purchasable = TestPurchasable::create(['price' => '10.000']);

    $order = $buyer->purchase($purchasable);

    expect($buyer->hasPurchased($purchasable))->toBeFalse()
        ->and($buyer->hasPendingOrderFor($purchasable))->toBeTrue();

    app(RecordTransaction::class)->execute(
        order: $order,
        type: TransactionType::Charge,
        gateway: 'manual',
        amount: $order->amount,
        status: TransactionStatus::Success,
    );

    expect($buyer->hasPurchased($purchasable))->toBeTrue()
        ->and($buyer->hasPendingOrderFor($purchasable))->toBeFalse();
});

it('refuses a second purchase while a pending order already exists', function () {
    $buyer = TestBuyer::create();
    $purchasable = TestPurchasable::create(['price' => '10.000']);

    $buyer->purchase($purchasable);

    expect(fn () => $buyer->purchase($purchasable))
        ->toThrow(AlreadyPurchasedException::class);
});

it('refuses a purchase of an already-paid item', function () {
    $buyer = TestBuyer::create();
    $purchasable = TestPurchasable::create(['price' => '10.000']);

    $order = $buyer->purchase($purchasable);

    app(RecordTransaction::class)->execute(
        order: $order,
        type: TransactionType::Charge,
        gateway: 'manual',
        amount: $order->amount,
        status: TransactionStatus::Success,
    );

    expect(fn () => $buyer->purchase($purchasable))
        ->toThrow(AlreadyPurchasedException::class);
});

it('allows a second pending order when allow_multiple_pending_orders is enabled', function () {
    config()->set('purchasable.allow_multiple_pending_orders', true);

    $buyer = TestBuyer::create();
    $purchasable = TestPurchasable::create(['price' => '10.000']);

    $buyer->purchase($purchasable);
    $second = $buyer->purchase($purchasable);

    expect($second)->toBeInstanceOf(Order::class);
    expect($buyer->orders()->count())->toBe(2);
});

it('allows repurchasing an already-paid item when allow_repurchase is enabled', function () {
    config()->set('purchasable.allow_repurchase', true);

    $buyer = TestBuyer::create();
    $purchasable = TestPurchasable::create(['price' => '10.000']);

    $order = $buyer->purchase($purchasable);

    app(RecordTransaction::class)->execute(
        order: $order,
        type: TransactionType::Charge,
        gateway: 'manual',
        amount: $order->amount,
        status: TransactionStatus::Success,
    );

    $second = $buyer->purchase($purchasable);

    expect($second)->toBeInstanceOf(Order::class);
    expect($buyer->orders()->count())->toBe(2);
});
