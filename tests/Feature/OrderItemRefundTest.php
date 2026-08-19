<?php

use Amzad\LaravelPurchasable\Actions\RecordTransaction;
use Amzad\LaravelPurchasable\Actions\RefundOrder;
use Amzad\LaravelPurchasable\Enums\OrderItemStatus;
use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Events\OrderItemPartiallyRefunded;
use Amzad\LaravelPurchasable\Events\OrderItemRefunded;
use Amzad\LaravelPurchasable\Events\OrderPartiallyRefunded;
use Amzad\LaravelPurchasable\Events\OrderRefunded;
use Amzad\LaravelPurchasable\Exceptions\OrderItemMismatchException;
use Amzad\LaravelPurchasable\Exceptions\RefundExceedsPaidAmountException;
use Amzad\LaravelPurchasable\States\Order\PaidState;
use Amzad\LaravelPurchasable\States\Order\PartiallyRefundedState;
use Amzad\LaravelPurchasable\States\Order\RefundedState;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestBuyer;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestPurchasable;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestSubscribable;
use Illuminate\Support\Facades\Event;

function createPaidMultiItemOrder(): array
{
    $buyer = TestBuyer::create();
    $product = TestPurchasable::create(['price' => '10.000']);
    $subscription = TestSubscribable::create(['price' => '25.000']);

    $order = $buyer->purchaseMultiple([
        ['purchasable' => $product, 'quantity' => 1],
        ['purchasable' => $subscription, 'quantity' => 1],
    ]);

    app(RecordTransaction::class)->execute(
        order: $order,
        type: TransactionType::Charge,
        gateway: 'manual',
        amount: $order->amount,
        status: TransactionStatus::Success,
    );

    $order->refresh();
    $itemA = $order->items()->where('purchasable_type', $product->getMorphClass())->first();
    $itemB = $order->items()->where('purchasable_type', $subscription->getMorphClass())->first();

    return [$order, $itemA, $itemB];
}

it('fully refunds one item out of a multi-item order without touching the other', function () {
    [$order, $itemA, $itemB] = createPaidMultiItemOrder();

    app(RefundOrder::class)->execute($order, TransactionStatus::Success, item: $itemA);

    $itemA->refresh();
    $itemB->refresh();
    $order->refresh();

    expect($itemA->isFullyRefunded())->toBeTrue()
        ->and($itemA->status)->toBe(OrderItemStatus::Cancelled)
        ->and($itemB->isFullyRefunded())->toBeFalse()
        ->and($itemB->status)->toBe(OrderItemStatus::Active)
        ->and($order->status->equals(PartiallyRefundedState::class))->toBeTrue();
});

it('partially refunds a single item and leaves it Active', function () {
    [$order, $itemA] = createPaidMultiItemOrder();

    app(RefundOrder::class)->execute($order, TransactionStatus::Success, amount: '4.000', item: $itemA);

    $itemA->refresh();

    expect($itemA->isPartiallyRefunded())->toBeTrue()
        ->and($itemA->status)->toBe(OrderItemStatus::Active)
        ->and($itemA->refundedAmount())->toBe('4.000');
});

it('rejects an item-scoped refund that exceeds that item amount even though the order has balance left', function () {
    [$order, $itemA] = createPaidMultiItemOrder();

    // itemA is 10.000; the order overall has 35.000 unrefunded, so an
    // order-level check alone would allow this — the item-level guard must
    // catch it independently.
    expect(fn () => app(RefundOrder::class)->execute($order, TransactionStatus::Success, amount: '15.000', item: $itemA))
        ->toThrow(RefundExceedsPaidAmountException::class);
});

it('moves the order to Refunded once every item has been fully refunded', function () {
    [$order, $itemA, $itemB] = createPaidMultiItemOrder();

    app(RefundOrder::class)->execute($order, TransactionStatus::Success, item: $itemA);
    app(RefundOrder::class)->execute($order->refresh(), TransactionStatus::Success, item: $itemB);

    expect($order->refresh()->status->equals(RefundedState::class))->toBeTrue();
});

it('fires OrderItemRefunded and OrderItemPartiallyRefunded alongside the order-level events', function () {
    [$order, $itemA, $itemB] = createPaidMultiItemOrder();

    Event::fake([OrderItemRefunded::class, OrderItemPartiallyRefunded::class, OrderPartiallyRefunded::class, OrderRefunded::class]);

    app(RefundOrder::class)->execute($order, TransactionStatus::Success, item: $itemA);

    Event::assertDispatchedTimes(OrderItemRefunded::class, 1);
    Event::assertDispatched(fn (OrderItemRefunded $event) => $event->item->is($itemA));
    // itemB is still unrefunded, so the order as a whole is only partially refunded.
    Event::assertDispatchedTimes(OrderPartiallyRefunded::class, 1);
    Event::assertNotDispatched(OrderRefunded::class);
});

it('does not scope transactions to an item for the normal whole-order flow', function () {
    [$order] = createPaidMultiItemOrder();

    $charge = $order->transactions()->where('type', TransactionType::Charge)->first();

    expect($charge->order_item_id)->toBeNull();
});

it('rejects an item that does not belong to the given order', function () {
    [$orderOne, $itemFromOrderOne] = createPaidMultiItemOrder();
    [$orderTwo] = createPaidMultiItemOrder();

    expect(fn () => app(RefundOrder::class)->execute($orderTwo, TransactionStatus::Success, item: $itemFromOrderOne))
        ->toThrow(OrderItemMismatchException::class);
});

it('single-item orders remain untouched: transactions still record with a null order_item_id', function () {
    $buyer = TestBuyer::create();
    $product = TestPurchasable::create(['price' => '10.000']);
    $order = $buyer->purchase($product);

    app(RecordTransaction::class)->execute(
        order: $order,
        type: TransactionType::Charge,
        gateway: 'manual',
        amount: $order->amount,
        status: TransactionStatus::Success,
    );

    $order->refresh();

    expect($order->status->equals(PaidState::class))->toBeTrue()
        ->and($order->transactions()->first()->order_item_id)->toBeNull();
});
