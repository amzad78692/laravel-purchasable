<?php

namespace Amzad\LaravelPurchasable\Actions;

use Amzad\LaravelPurchasable\Contracts\Buyer as BuyerContract;
use Amzad\LaravelPurchasable\Contracts\Purchasable as PurchasableContract;
use Amzad\LaravelPurchasable\Events\OrderCreated;
use Amzad\LaravelPurchasable\Exceptions\AlreadyPurchasedException;
use Amzad\LaravelPurchasable\Exceptions\GuestCheckoutException;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Support\GuestCheckout;
use Amzad\LaravelPurchasable\Support\OrderNumberGenerator;
use Illuminate\Database\Eloquent\Model;

class CreateOrder
{
    public function __construct(protected OrderNumberGenerator $orderNumbers) {}

    /**
     * Pass a null $buyer together with $options['guest'] to create a guest order.
     *
     * @param  array{quantity?: int, amount?: string, currency?: string, meta?: array, guest?: array{email: string, name?: string|null, phone?: string|null}}  $options
     *
     * @throws AlreadyPurchasedException
     * @throws GuestCheckoutException
     */
    public function execute(Model&PurchasableContract $purchasable, (Model&BuyerContract)|null $buyer, array $options = []): Order
    {
        $attributes = $buyer === null
            ? $this->guestAttributes($purchasable, $options['guest'] ?? [])
            : $this->buyerAttributes($purchasable, $buyer);

        $quantity = (int) ($options['quantity'] ?? 1);
        $currency = $options['currency'] ?? $this->purchasableCurrency($purchasable);
        $amount = $options['amount'] ?? bcmul((string) $purchasable->getPurchasablePrice(), (string) $quantity, 3);

        $order = Order::create([
            'order_number' => $this->orderNumbers->generate('order_number'),
            ...$attributes,
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

    /**
     * @return array<string, mixed>
     */
    protected function buyerAttributes(Model&PurchasableContract $purchasable, Model&BuyerContract $buyer): array
    {
        if (
            (! config('purchasable.allow_repurchase', false) && $buyer->hasPurchased($purchasable))
            || (! config('purchasable.allow_multiple_pending_orders', false) && $buyer->hasPendingOrderFor($purchasable))
        ) {
            throw AlreadyPurchasedException::for($buyer, $purchasable);
        }

        return [
            'buyer_type' => $buyer->getMorphClass(),
            'buyer_id' => $buyer->getKey(),
        ];
    }

    /**
     * @param  array{email?: string, name?: string|null, phone?: string|null}  $guest
     * @return array<string, mixed>
     */
    protected function guestAttributes(Model&PurchasableContract $purchasable, array $guest): array
    {
        $attributes = GuestCheckout::attributes($guest);

        GuestCheckout::guardAgainstDuplicate($attributes['guest_email'], $purchasable);

        return $attributes;
    }

    protected function purchasableCurrency(Model $purchasable): string
    {
        return method_exists($purchasable, 'getPurchasableCurrency')
            ? $purchasable->getPurchasableCurrency()
            : config('purchasable.default_currency');
    }
}
