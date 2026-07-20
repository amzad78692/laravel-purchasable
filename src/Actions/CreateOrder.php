<?php

namespace Amzad\LaravelPurchasable\Actions;

use Amzad\LaravelPurchasable\Contracts\Buyer as BuyerContract;
use Amzad\LaravelPurchasable\Contracts\Purchasable as PurchasableContract;
use Amzad\LaravelPurchasable\Events\OrderCreated;
use Amzad\LaravelPurchasable\Exceptions\AlreadyPurchasedException;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Support\OrderNumberGenerator;
use Illuminate\Database\Eloquent\Model;

class CreateOrder
{
    public function __construct(protected OrderNumberGenerator $orderNumbers) {}

    /**
     * @param  array{quantity?: int, amount?: string, currency?: string, meta?: array}  $options
     */
    public function execute(Model&PurchasableContract $purchasable, Model&BuyerContract $buyer, array $options = []): Order
    {
        if (
            (! config('purchasable.allow_repurchase', false) && $buyer->hasPurchased($purchasable))
            || (! config('purchasable.allow_multiple_pending_orders', false) && $buyer->hasPendingOrderFor($purchasable))
        ) {
            throw AlreadyPurchasedException::for($buyer, $purchasable);
        }

        $quantity = (int) ($options['quantity'] ?? 1);
        $currency = $options['currency'] ?? $this->purchasableCurrency($purchasable);
        $amount = $options['amount'] ?? bcmul((string) $purchasable->getPurchasablePrice(), (string) $quantity, 3);

        $order = Order::create([
            'order_number' => $this->orderNumbers->generate('order_number'),
            'buyer_type' => $buyer->getMorphClass(),
            'buyer_id' => $buyer->getKey(),
            'purchasable_type' => $purchasable->getMorphClass(),
            'purchasable_id' => $purchasable->getKey(),
            'quantity' => $quantity,
            'amount' => $amount,
            'currency' => $currency,
            'meta' => $options['meta'] ?? [],
        ]);

        event(new OrderCreated($order));

        return $order;
    }

    protected function purchasableCurrency(Model $purchasable): string
    {
        return method_exists($purchasable, 'getPurchasableCurrency')
            ? $purchasable->getPurchasableCurrency()
            : config('purchasable.default_currency');
    }
}
