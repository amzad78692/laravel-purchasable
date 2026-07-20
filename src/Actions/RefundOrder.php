<?php

namespace Amzad\LaravelPurchasable\Actions;

use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Models\Transaction;
use RuntimeException;

/**
 * Records a refund transaction. Like charges, the host determines the
 * outcome (however they process the actual refund) and passes it in — this
 * action just defaults the amount to the order's remaining unrefunded
 * balance and the gateway to the one used on the original charge.
 */
class RefundOrder
{
    public function __construct(protected RecordTransaction $recordTransaction) {}

    /**
     * @param  string|null  $amount  defaults to the remaining unrefunded balance (full refund)
     * @param  string|null  $gateway  defaults to the order's last successful charge's gateway
     */
    public function execute(
        Order $order,
        TransactionStatus $status,
        ?string $amount = null,
        ?string $gateway = null,
        ?string $gatewayTransactionId = null,
        array $responsePayload = [],
    ): Transaction {
        $amount ??= bcsub((string) $order->amount, $order->refundedAmount(), 3);
        $gateway ??= $this->latestSuccessfulCharge($order)->gateway;

        return $this->recordTransaction->execute(
            order: $order,
            type: TransactionType::Refund,
            gateway: $gateway,
            amount: $amount,
            status: $status,
            gatewayTransactionId: $gatewayTransactionId,
            currency: $order->currency,
            responsePayload: $responsePayload,
        );
    }

    protected function latestSuccessfulCharge(Order $order): Transaction
    {
        $charge = $order->transactions()
            ->where('type', TransactionType::Charge)
            ->where('status', TransactionStatus::Success)
            ->latest('id')
            ->first();

        if ($charge === null) {
            throw new RuntimeException("Order [{$order->getKey()}] has no successful charge to refund.");
        }

        return $charge;
    }
}
