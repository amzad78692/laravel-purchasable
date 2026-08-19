<?php

use Amzad\LaravelPurchasable\Actions\RecordTransaction;
use Amzad\LaravelPurchasable\Enums\OrderItemStatus;
use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Events\OrderCreated;
use Amzad\LaravelPurchasable\Exceptions\AlreadyPurchasedException;
use Amzad\LaravelPurchasable\Exceptions\CurrencyMismatchException;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestBuyer;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestPurchasable;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestSubscribable;
use Illuminate\Support\Facades\Event;

it('creates a multi-item order mixing two purchasable types', function () {
    $buyer = TestBuyer::create();
    $product = TestPurchasable::create(['price' => '10.000']);
    $subscription = TestSubscribable::create(['price' => '25.000']);

    $order = $buyer->purchaseMultiple([
        ['purchasable' => $product, 'quantity' => 2],
        ['purchasable' => $subscription, 'quantity' => 1],
    ]);

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->purchasable_type)->toBeNull()
        ->and($order->purchasable_id)->toBeNull()
        ->and($order->quantity)->toBeNull()
        ->and($order->isMultiItem())->toBeTrue()
        ->and($order->amount)->toBe('45.000')
        ->and($order->items()->count())->toBe(2);

    $productItem = $order->items()->where('purchasable_type', $product->getMorphClass())->first();
    $subscriptionItem = $order->items()->where('purchasable_type', $subscription->getMorphClass())->first();

    expect($productItem->quantity)->toBe(2)
        ->and($productItem->unit_price)->toBe('10.000')
        ->and($productItem->amount)->toBe('20.000')
        ->and($productItem->status)->toBe(OrderItemStatus::Active)
        ->and($subscriptionItem->amount)->toBe('25.000');
});

it('defaults unit_price to the purchasable price when not given explicitly', function () {
    $buyer = TestBuyer::create();
    $product = TestPurchasable::create(['price' => '10.000']);

    $order = $buyer->purchaseMultiple([
        ['purchasable' => $product, 'quantity' => 3, 'unit_price' => '9.500'],
    ]);

    $item = $order->items()->first();

    expect($item->unit_price)->toBe('9.500')
        ->and($item->amount)->toBe('28.500')
        ->and($order->amount)->toBe('28.500');
});

it('throws CurrencyMismatchException when an explicit order currency disagrees with an item', function () {
    $buyer = TestBuyer::create();
    // TestPurchasable has no getPurchasableCurrency(), so it resolves to
    // config('purchasable.default_currency') (KWD) — an explicit 'USD'
    // option therefore mismatches this item's resolved currency.
    $product = TestPurchasable::create(['price' => '10.000']);

    expect(fn () => $buyer->purchaseMultiple([
        ['purchasable' => $product, 'quantity' => 1],
    ], ['currency' => 'USD']))
        ->toThrow(CurrencyMismatchException::class);
});

it('does not enforce allow_repurchase or allow_multiple_pending_orders by default', function () {
    $buyer = TestBuyer::create();
    $product = TestPurchasable::create(['price' => '10.000']);

    $first = $buyer->purchase($product);
    app(RecordTransaction::class)->execute(
        order: $first,
        type: TransactionType::Charge,
        gateway: 'manual',
        amount: $first->amount,
        status: TransactionStatus::Success,
    );

    expect($buyer->hasPurchased($product))->toBeTrue();

    $cart = $buyer->purchaseMultiple([
        ['purchasable' => $product, 'quantity' => 1],
    ]);

    expect($cart)->toBeInstanceOf(Order::class);
});

it('enforces the per-item purchase guards when multi_item.enforce_purchase_guards is enabled', function () {
    config()->set('purchasable.multi_item.enforce_purchase_guards', true);

    $buyer = TestBuyer::create();
    $product = TestPurchasable::create(['price' => '10.000']);

    $first = $buyer->purchase($product);
    app(RecordTransaction::class)->execute(
        order: $first,
        type: TransactionType::Charge,
        gateway: 'manual',
        amount: $first->amount,
        status: TransactionStatus::Success,
    );

    expect(fn () => $buyer->purchaseMultiple([
        ['purchasable' => $product, 'quantity' => 1],
    ]))->toThrow(AlreadyPurchasedException::class);
});

it('fires OrderCreated exactly once for a multi-item order', function () {
    Event::fake([OrderCreated::class]);

    $buyer = TestBuyer::create();
    $product = TestPurchasable::create(['price' => '10.000']);
    $subscription = TestSubscribable::create(['price' => '25.000']);

    $order = $buyer->purchaseMultiple([
        ['purchasable' => $product, 'quantity' => 1],
        ['purchasable' => $subscription, 'quantity' => 1],
    ]);

    Event::assertDispatchedTimes(OrderCreated::class, 1);
    Event::assertDispatched(fn (OrderCreated $event) => $event->order->is($order));
});
