<?php

namespace Amzad\LaravelPurchasable\Actions;

use Amzad\LaravelPurchasable\Enums\OrderItemStatus;
use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Events\OrderItemPartiallyRefunded;
use Amzad\LaravelPurchasable\Events\OrderItemRefunded;
use Amzad\LaravelPurchasable\Events\OrderPaid;
use Amzad\LaravelPurchasable\Events\OrderPartiallyRefunded;
use Amzad\LaravelPurchasable\Events\OrderRefunded;
use Amzad\LaravelPurchasable\Events\TransactionFailed;
use Amzad\LaravelPurchasable\Events\TransactionRecorded;
use Amzad\LaravelPurchasable\Exceptions\CurrencyMismatchException;
use Amzad\LaravelPurchasable\Exceptions\OrderItemMismatchException;
use Amzad\LaravelPurchasable\Exceptions\OrderTrashedException;
use Amzad\LaravelPurchasable\Exceptions\RefundExceedsPaidAmountException;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Models\OrderItem;
use Amzad\LaravelPurchasable\Models\Transaction;
use Amzad\LaravelPurchasable\States\Order\PaidState;
use Amzad\LaravelPurchasable\States\Order\PartiallyRefundedState;
use Amzad\LaravelPurchasable\States\Order\PendingState;
use Amzad\LaravelPurchasable\Support\OrderNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RecordTransaction
{
    public function __construct(protected OrderNumberGenerator $numbers) {}

    /**
     * @param  OrderItem|null  $item  scopes this transaction to a single line item of a multi-item order (refunds only — see RefundOrder). Leave null for the normal, whole-order flow.
     */
    public function execute(
        Order $order,
        TransactionType $type,
        string $gateway,
        string $amount,
        TransactionStatus $status,
        ?string $gatewayTransactionId = null,
        ?string $currency = null,
        array $responsePayload = [],
        ?OrderItem $item = null,
    ): Transaction {
        if ($order->trashed()) {
            throw OrderTrashedException::for($order);
        }

        if ($item !== null && $item->order_id !== $order->getKey()) {
            throw OrderItemMismatchException::for($order, $item);
        }

        $currency ??= $order->currency;

        if ($currency !== $order->currency) {
            throw CurrencyMismatchException::for($order, $currency);
        }

        if ($gatewayTransactionId !== null) {
            $existing = $this->findByGatewayId($gateway, $gatewayTransactionId);

            if ($existing !== null) {
                return $existing;
            }
        }

        [$transaction, $orderEvent, $itemEvent] = DB::transaction(function () use (
            $order, $item, $type, $gateway, $amount, $status, $gatewayTransactionId, $currency, $responsePayload,
        ) {
            $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $lockedItem = $item !== null
                ? OrderItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail()
                : null;

            if ($type === TransactionType::Refund && $status === TransactionStatus::Success) {
                $alreadyRefunded = $lockedOrder->refundedAmount();

                if (bccomp(bcadd($alreadyRefunded, $amount, 3), (string) $lockedOrder->amount, 3) > 0) {
                    throw RefundExceedsPaidAmountException::for($lockedOrder, $alreadyRefunded, $amount);
                }

                if ($lockedItem !== null) {
                    $alreadyRefundedItem = $lockedItem->refundedAmount();

                    if (bccomp(bcadd($alreadyRefundedItem, $amount, 3), (string) $lockedItem->amount, 3) > 0) {
                        throw RefundExceedsPaidAmountException::forItem($lockedItem, $alreadyRefundedItem, $amount);
                    }
                }
            }

            try {
                $transaction = $lockedOrder->transactions()->create([
                    'transaction_number' => $this->numbers->generate('transaction_number'),
                    'order_item_id' => $lockedItem?->getKey(),
                    'type' => $type,
                    'gateway' => $gateway,
                    'gateway_transaction_id' => $gatewayTransactionId,
                    'status' => $status,
                    'amount' => $amount,
                    'currency' => $currency,
                    'response_payload' => $responsePayload,
                    'paid_at' => $status === TransactionStatus::Success ? now() : null,
                ]);
            } catch (QueryException $exception) {
                if ($gatewayTransactionId !== null && $this->isDuplicateKeyError($exception)) {
                    return [$this->findByGatewayId($gateway, $gatewayTransactionId), null, null];
                }

                throw $exception;
            }

            [, $orderEvent] = $this->applyOrderTransition($lockedOrder, $type, $status);

            $itemEvent = null;

            if ($lockedItem !== null) {
                [, $itemEvent] = $this->applyItemTransition($lockedItem, $type, $status);
            }

            return [$transaction, $orderEvent, $itemEvent];
        });

        event(new TransactionRecorded($transaction));

        if ($status === TransactionStatus::Failed) {
            event(new TransactionFailed($transaction));
        }

        if ($orderEvent !== null) {
            event($orderEvent);
        }

        if ($itemEvent !== null) {
            event($itemEvent);
        }

        $order->refresh();

        return $transaction;
    }

    /**
     * A failed charge does NOT move the order to Failed — retries are expected
     * (failed, failed, success is a normal sequence) and the package doesn't
     * guess at a host's retry-limit policy. Explicitly failing an order after
     * giving up retries is what Actions\FailOrder is for.
     *
     * @return array{0: Order, 1: OrderPaid|OrderPartiallyRefunded|OrderRefunded|null}
     */
    protected function applyOrderTransition(Order $order, TransactionType $type, TransactionStatus $status): array
    {
        if ($type === TransactionType::Charge && $status === TransactionStatus::Success && $order->status->equals(PendingState::class)) {
            $order = $order->markAsPaid();

            return [$order, new OrderPaid($order)];
        }

        if ($type === TransactionType::Refund && $status === TransactionStatus::Success) {
            if ($order->isFullyRefunded()) {
                if ($order->status->equals(PaidState::class) || $order->status->equals(PartiallyRefundedState::class)) {
                    $order = $order->markAsRefunded();

                    return [$order, new OrderRefunded($order)];
                }
            } elseif ($order->status->equals(PaidState::class)) {
                $order = $order->markAsPartiallyRefunded();

                return [$order, new OrderPartiallyRefunded($order)];
            }
        }

        return [$order, null];
    }

    /**
     * Mirrors applyOrderTransition() but scoped to a single line item.
     * Deliberately independent of the order's own status: refunding one
     * item out of a multi-item order can fire OrderItemRefunded for that
     * item while the order as a whole only reaches PartiallyRefundedState,
     * because other items are still unrefunded.
     *
     * @return array{0: OrderItem, 1: OrderItemPartiallyRefunded|OrderItemRefunded|null}
     */
    protected function applyItemTransition(OrderItem $item, TransactionType $type, TransactionStatus $status): array
    {
        if ($type === TransactionType::Refund && $status === TransactionStatus::Success) {
            if ($item->isFullyRefunded()) {
                $item->update(['status' => OrderItemStatus::Cancelled]);

                return [$item, new OrderItemRefunded($item)];
            }

            return [$item, new OrderItemPartiallyRefunded($item)];
        }

        return [$item, null];
    }

    protected function findByGatewayId(string $gateway, string $gatewayTransactionId): ?Transaction
    {
        return Transaction::query()
            ->where('gateway', $gateway)
            ->where('gateway_transaction_id', $gatewayTransactionId)
            ->first();
    }

    protected function isDuplicateKeyError(QueryException $exception): bool
    {
        return in_array($exception->getCode(), ['23000', '23505'], true);
    }
}
