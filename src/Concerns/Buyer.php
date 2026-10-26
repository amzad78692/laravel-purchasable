<?php

namespace Amzad\LaravelPurchasable\Concerns;

use Amzad\LaravelPurchasable\Actions\CreateMultiItemOrder;
use Amzad\LaravelPurchasable\Actions\CreateOrder;
use Amzad\LaravelPurchasable\Contracts\Purchasable as PurchasableContract;
use Amzad\LaravelPurchasable\Events\OrderClaimed;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\PaidState;
use Amzad\LaravelPurchasable\States\Order\PendingState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements \Amzad\LaravelPurchasable\Contracts\Buyer
 */
trait Buyer
{
    public function orders(): MorphMany
    {
        return $this->morphMany(Order::class, 'buyer');
    }

    public function hasPurchased(Model $purchasable): bool
    {
        return $this->orders()
            ->where('purchasable_type', $purchasable->getMorphClass())
            ->where('purchasable_id', $purchasable->getKey())
            ->whereState('status', PaidState::class)
            ->exists();
    }

    public function hasPendingOrderFor(Model $purchasable): bool
    {
        return $this->orders()
            ->where('purchasable_type', $purchasable->getMorphClass())
            ->where('purchasable_id', $purchasable->getKey())
            ->whereState('status', PendingState::class)
            ->exists();
    }

    public function purchase(Model&PurchasableContract $purchasable, array $options = []): Order
    {
        return app(CreateOrder::class)->execute($purchasable, $this, $options);
    }

    /**
     * Creates a multi-item ("cart-style") order in one atomic call — see
     * CreateMultiItemOrder for the $items shape and $options.
     *
     * @param  array<int, array{purchasable: Model&PurchasableContract, quantity?: int, unit_price?: string|int|float, meta?: array}>  $items
     * @param  array{currency?: string, meta?: array}  $options
     */
    public function purchaseMultiple(array $items, array $options = []): Order
    {
        return app(CreateMultiItemOrder::class)->execute($items, $this, $options);
    }

    /**
     * Paid orders, most recent first — a ready-made "purchase history" list.
     *
     * @return MorphMany<Order, $this>
     */
    public function purchaseHistory(): MorphMany
    {
        return $this->orders()->whereState('status', PaidState::class)->latest('paid_at');
    }

    /**
     * Attach guest orders placed with this email to this buyer and revoke
     * their access tokens. Only call this once the email is verified as
     * belonging to this buyer, or anyone could claim someone else's orders.
     *
     * @return int number of orders claimed
     */
    public function claimGuestOrders(?string $email = null): int
    {
        $email ??= $this->getAttribute('email');

        if (blank($email)) {
            return 0;
        }

        $claimed = 0;

        // get() rather than each(): each() pages by offset, and every save
        // drops a row out of the guest() filter, which would skip rows.
        Order::query()->guest()->forGuest($email)->get()->each(function (Order $order) use (&$claimed) {
            $order->forceFill([
                'buyer_type' => $this->getMorphClass(),
                'buyer_id' => $this->getKey(),
                'access_token' => null,
            ])->save();

            event(new OrderClaimed($order));

            $claimed++;
        });

        return $claimed;
    }
}
