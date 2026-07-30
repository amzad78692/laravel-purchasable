<?php

namespace Amzad\LaravelPurchasable\Actions;

use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Events\OrderPaid;
use Amzad\LaravelPurchasable\Events\OrderPartiallyRefunded;
use Amzad\LaravelPurchasable\Events\OrderRefunded;
use Amzad\LaravelPurchasable\Events\TransactionFailed;
use Amzad\LaravelPurchasable\Events\TransactionRecorded;
use Amzad\LaravelPurchasable\Exceptions\CurrencyMismatchException;
use Amzad\LaravelPurchasable\Exceptions\OrderTrashedException;
use Amzad\LaravelPurchasable\Exceptions\RefundExceedsPaidAmountException;
use Amzad\LaravelPurchasable\Models\Order;
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

    public function execute(
        Order $order,
        TransactionType $type,
        string $gateway,
        string $amount,
        TransactionStatus $status,
        ?string $gatewayTransactionId = null,
        ?string $currency = null,
        array $responsePayload = [],
    ): Transaction {
        if ($order->trashed()) {
            throw OrderTrashedException::for($order);
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

        [$transaction, $orderEvent] = DB::transaction(function () use (
            $order, $type, $gateway, $amount, $status, $gatewayTransactionId, $currency, $responsePayload,
        ) {
            $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($type === TransactionType::Refund && $status === TransactionStatus::Success) {
                $alreadyRefunded = $lockedOrder->refundedAmount();

                if (bccomp(bcadd($alreadyRefunded, $amount, 3), (string) $lockedOrder->amount, 3) > 0) {
                    throw RefundExceedsPaidAmountException::for($lockedOrder, $alreadyRefunded, $amount);
                }
            }

            try {
                $transaction = $lockedOrder->transactions()->create([
                    'transaction_number' => $this->numbers->generate('transaction_number'),
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
                    return [$this->findByGatewayId($gateway, $gatewayTransactionId), null];
                }

                throw $exception;
            }

            [, $orderEvent] = $this->applyOrderTransition($lockedOrder, $type, $status);

            return [$transaction, $orderEvent];
        });

        event(new TransactionRecorded($transaction));

        if ($status === TransactionStatus::Failed) {
            event(new TransactionFailed($transaction));
        }

        if ($orderEvent !== null) {
            event($orderEvent);
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
