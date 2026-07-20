<?php

use Amzad\LaravelPurchasable\Actions\RecordTransaction;
use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Exceptions\CurrencyMismatchException;
use Amzad\LaravelPurchasable\Exceptions\OrderTrashedException;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Models\Transaction;
use Amzad\LaravelPurchasable\States\Order\PaidState;
use Amzad\LaravelPurchasable\States\Order\PendingState;

it('moves a pending order to paid on a successful charge', function () {
    $order = Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']);

    $transaction = app(RecordTransaction::class)->execute(
        order: $order,
        type: TransactionType::Charge,
        gateway: 'manual',
        amount: '20.000',
        status: TransactionStatus::Success,
    );

    expect($transaction)->toBeInstanceOf(Transaction::class);

    $order->refresh();

    expect($order->status->equals(PaidState::class))->toBeTrue()
        ->and($order->paid_at)->not->toBeNull();
});

it('keeps the order pending through failed retries, then pays on the eventual success', function () {
    $order = Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']);

    app(RecordTransaction::class)->execute($order, TransactionType::Charge, 'manual', '20.000', TransactionStatus::Failed);
    $order->refresh();
    expect($order->status->equals(PendingState::class))->toBeTrue();

    app(RecordTransaction::class)->execute($order, TransactionType::Charge, 'manual', '20.000', TransactionStatus::Failed);
    $order->refresh();
    expect($order->status->equals(PendingState::class))->toBeTrue();

    app(RecordTransaction::class)->execute($order, TransactionType::Charge, 'manual', '20.000', TransactionStatus::Success);
    $order->refresh();
    expect($order->status->equals(PaidState::class))->toBeTrue();

    expect($order->transactions()->count())->toBe(3);
});

it('is idempotent for duplicate gateway transaction ids', function () {
    $order = Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']);

    $first = app(RecordTransaction::class)->execute(
        order: $order,
        type: TransactionType::Charge,
        gateway: 'stripe',
        amount: '20.000',
        status: TransactionStatus::Success,
        gatewayTransactionId: 'ch_123',
    );

    $second = app(RecordTransaction::class)->execute(
        order: $order,
        type: TransactionType::Charge,
        gateway: 'stripe',
        amount: '20.000',
        status: TransactionStatus::Success,
        gatewayTransactionId: 'ch_123',
    );

    expect($second->is($first))->toBeTrue();
    expect(Transaction::query()->where('gateway_transaction_id', 'ch_123')->count())->toBe(1);
});

it('rejects a transaction whose currency does not match the order', function () {
    $order = Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']);

    expect(fn () => app(RecordTransaction::class)->execute(
        order: $order,
        type: TransactionType::Charge,
        gateway: 'manual',
        amount: '20.000',
        status: TransactionStatus::Success,
        currency: 'USD',
    ))->toThrow(CurrencyMismatchException::class);
});

it('refuses to record a transaction against a soft-deleted order', function () {
    $order = Order::factory()->pending()->create(['amount' => '20.000', 'currency' => 'KWD']);
    $order->delete();

    expect(fn () => app(RecordTransaction::class)->execute(
        order: $order,
        type: TransactionType::Charge,
        gateway: 'manual',
        amount: '20.000',
        status: TransactionStatus::Success,
    ))->toThrow(OrderTrashedException::class);
});
