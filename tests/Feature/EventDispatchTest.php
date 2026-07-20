<?php

use Amzad\LaravelPurchasable\Actions\CancelOrder;
use Amzad\LaravelPurchasable\Actions\FailOrder;
use Amzad\LaravelPurchasable\Actions\RecordTransaction;
use Amzad\LaravelPurchasable\Actions\RefundOrder;
use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Events\OrderCancelled;
use Amzad\LaravelPurchasable\Events\OrderCreated;
use Amzad\LaravelPurchasable\Events\OrderFailed;
use Amzad\LaravelPurchasable\Events\OrderPaid;
use Amzad\LaravelPurchasable\Events\OrderPartiallyRefunded;
use Amzad\LaravelPurchasable\Events\OrderRefunded;
use Amzad\LaravelPurchasable\Events\TransactionFailed;
use Amzad\LaravelPurchasable\Events\TransactionRecorded;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestBuyer;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestPurchasable;
use Illuminate\Support\Facades\Event;

it('fires OrderCreated when an order is created', function () {
    Event::fake([OrderCreated::class]);

    $buyer = TestBuyer::create();
    $purchasable = TestPurchasable::create(['price' => '10.000']);
    $order = $buyer->purchase($purchasable);

    Event::assertDispatchedTimes(OrderCreated::class, 1);
    Event::assertDispatched(fn (OrderCreated $event) => $event->order->is($order));
});

it('fires TransactionRecorded and OrderPaid on a successful charge', function () {
    Event::fake([TransactionRecorded::class, OrderPaid::class]);

    $order = Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']);

    app(RecordTransaction::class)->execute($order, TransactionType::Charge, 'manual', '20.000', TransactionStatus::Success);

    Event::assertDispatchedTimes(TransactionRecorded::class, 1);
    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

it('fires TransactionRecorded and TransactionFailed, but not an order event, on a failed charge', function () {
    Event::fake([TransactionRecorded::class, TransactionFailed::class, OrderPaid::class, OrderFailed::class]);

    $order = Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']);

    app(RecordTransaction::class)->execute($order, TransactionType::Charge, 'manual', '20.000', TransactionStatus::Failed);

    Event::assertDispatchedTimes(TransactionRecorded::class, 1);
    Event::assertDispatchedTimes(TransactionFailed::class, 1);
    Event::assertNotDispatched(OrderPaid::class);
    Event::assertNotDispatched(OrderFailed::class);
});

it('fires OrderFailed when a host explicitly fails an order', function () {
    Event::fake([OrderFailed::class]);

    $order = Order::factory()->pending()->create();

    app(FailOrder::class)->execute($order);

    Event::assertDispatchedTimes(OrderFailed::class, 1);
});

it('fires OrderCancelled when an order is cancelled', function () {
    Event::fake([OrderCancelled::class]);

    $order = Order::factory()->pending()->create();

    app(CancelOrder::class)->execute($order);

    Event::assertDispatchedTimes(OrderCancelled::class, 1);
});

it('fires OrderRefunded on a full refund', function () {
    $order = Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']);

    app(RecordTransaction::class)->execute($order, TransactionType::Charge, 'manual', '20.000', TransactionStatus::Success);

    Event::fake([OrderRefunded::class, TransactionRecorded::class]);

    app(RefundOrder::class)->execute($order->refresh(), TransactionStatus::Success);

    Event::assertDispatchedTimes(OrderRefunded::class, 1);
});

it('fires OrderPartiallyRefunded, not OrderRefunded, on a partial refund', function () {
    $order = Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']);

    app(RecordTransaction::class)->execute($order, TransactionType::Charge, 'manual', '20.000', TransactionStatus::Success);

    Event::fake([OrderRefunded::class, OrderPartiallyRefunded::class, TransactionRecorded::class]);

    app(RefundOrder::class)->execute($order->refresh(), TransactionStatus::Success, amount: '5.000');

    Event::assertDispatchedTimes(OrderPartiallyRefunded::class, 1);
    Event::assertNotDispatched(OrderRefunded::class);
});
