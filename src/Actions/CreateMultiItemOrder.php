<?php

namespace Amzad\LaravelPurchasable\Actions;

use Amzad\LaravelPurchasable\Contracts\Buyer as BuyerContract;
use Amzad\LaravelPurchasable\Contracts\Purchasable as PurchasableContract;
use Amzad\LaravelPurchasable\Enums\OrderItemStatus;
use Amzad\LaravelPurchasable\Events\OrderCreated;
use Amzad\LaravelPurchasable\Exceptions\AlreadyPurchasedException;
use Amzad\LaravelPurchasable\Exceptions\CurrencyMismatchException;
use Amzad\LaravelPurchasable\Exceptions\GuestCheckoutException;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Support\GuestCheckout;
use Amzad\LaravelPurchasable\Support\OrderNumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates a multi-item ("cart-style") order: one Order with many OrderItem
 * rows, possibly mixing different purchasable types, in a single atomic
 * call. There's no persistent Cart entity — a host builds its own basket
 * however it likes and hands the finished list of items to execute().
 *
 * Unlike CreateOrder, this does NOT enforce allow_repurchase /
 * allow_multiple_pending_orders by default: those guards assume "one order
 * per purchasable" semantics that don't fit generic ecommerce carts, where
 * buying the same item more than once is normal. Set
 * purchasable.multi_item.enforce_purchase_guards to re-apply the same
 * per-item checks CreateOrder uses.
 *
 * Pass a null $buyer together with $options['guest'] to create a guest
 * order (requires purchasable.allow_guest_checkout).
 */
class CreateMultiItemOrder
{
    public function __construct(protected OrderNumberGenerator $orderNumbers) {}

    /**
     * @param  array<int, array{purchasable: Model&PurchasableContract, quantity?: int, unit_price?: string|int|float, meta?: array}>  $items
     * @param  array{currency?: string, meta?: array, guest?: array{email: string, name?: string|null, phone?: string|null}}  $options
     *
     * @throws GuestCheckoutException
     */
    public function execute(array $items, (Model&BuyerContract)|null $buyer, array $options = []): Order
    {
        if ($items === []) {
            throw new InvalidArgumentException('CreateMultiItemOrder::execute() requires at least one item.');
        }

        $buyerAttributes = $buyer === null
            ? GuestCheckout::attributes($options['guest'] ?? [])
            : ['buyer_type' => $buyer->getMorphClass(), 'buyer_id' => $buyer->getKey()];

        $enforceGuards = config('purchasable.multi_item.enforce_purchase_guards', false);

        if ($enforceGuards) {
            foreach ($items as $input) {
                $purchasable = $input['purchasable'];

                if ($buyer === null) {
                    GuestCheckout::guardAgainstDuplicate($buyerAttributes['guest_email'], $purchasable);

                    continue;
                }

                if (
                    (! config('purchasable.allow_repurchase', false) && $buyer->hasPurchased($purchasable))
                    || (! config('purchasable.allow_multiple_pending_orders', false) && $buyer->hasPendingOrderFor($purchasable))
                ) {
                    throw AlreadyPurchasedException::for($buyer, $purchasable);
                }
            }
        }

        return DB::transaction(function () use ($items, $buyerAttributes, $options) {
            $currency = $options['currency'] ?? $this->purchasableCurrency($items[0]['purchasable']);

            $order = Order::create([
                'order_number' => $this->orderNumbers->generate('order_number'),
                ...$buyerAttributes,
                'purchasable_type' => null,
                'purchasable_id' => null,
                'quantity' => null,
                'amount' => '0.000',
                'currency' => $currency,
                'meta' => $options['meta'] ?? [],
            ]);

            $total = '0.000';

            foreach ($items as $input) {
                $purchasable = $input['purchasable'];
                $itemCurrency = $this->purchasableCurrency($purchasable);

                if ($itemCurrency !== $currency) {
                    throw CurrencyMismatchException::for($order, $itemCurrency);
                }

                $quantity = (int) ($input['quantity'] ?? 1);
                $unitPrice = (string) ($input['unit_price'] ?? $purchasable->getPurchasablePrice());
                $amount = bcmul($unitPrice, (string) $quantity, 3);
                $total = bcadd($total, $amount, 3);

                $order->items()->create([
                    'purchasable_type' => $purchasable->getMorphClass(),
                    'purchasable_id' => $purchasable->getKey(),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => OrderItemStatus::Active,
                    'meta' => $input['meta'] ?? [],
                ]);
            }

            $order->update(['amount' => $total]);

            event(new OrderCreated($order));

            return $order;
        });
    }

    protected function purchasableCurrency(Model $purchasable): string
    {
        return method_exists($purchasable, 'getPurchasableCurrency')
            ? $purchasable->getPurchasableCurrency()
            : config('purchasable.default_currency');
    }
}
