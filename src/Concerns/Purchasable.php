<?php

namespace Amzad\LaravelPurchasable\Concerns;

use Amzad\LaravelPurchasable\Actions\CreateOrder;
use Amzad\LaravelPurchasable\Contracts\Buyer as BuyerContract;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\PaidState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements \Amzad\LaravelPurchasable\Contracts\Purchasable
 */
trait Purchasable
{
    /**
     * The unit price of this model, in the same currency returned by currency().
     * Not derived from a default column, since there's no safe guess that works
     * for every host model — Event, SubscriptionPlan, etc. all price differently.
     */
    abstract public function getPurchasablePrice(): string|int|float;

    public function orders(): MorphMany
    {
        return $this->morphMany(Order::class, 'purchasable');
    }

    public function price(): string|int|float
    {
        return $this->getPurchasablePrice();
    }

    public function currency(): string
    {
        return method_exists($this, 'getPurchasableCurrency')
            ? $this->getPurchasableCurrency()
            : config('purchasable.default_currency');
    }

    public function purchase(Model&BuyerContract $buyer, array $options = []): Order
    {
        return app(CreateOrder::class)->execute($this, $buyer, $options);
    }

    /**
     * @param  array{email: string, name?: string|null, phone?: string|null}  $guest
     */
    public function purchaseAsGuest(array $guest, array $options = []): Order
    {
        return app(CreateOrder::class)->execute($this, null, [...$options, 'guest' => $guest]);
    }

    public function isPurchasedByGuest(string $email): bool
    {
        return $this->orders()
            ->where('guest_email', Order::normalizeGuestEmail($email))
            ->whereState('status', PaidState::class)
            ->exists();
    }

    public function isPurchasedBy(Model $buyer): bool
    {
        return $this->orders()
            ->where('buyer_type', $buyer->getMorphClass())
            ->where('buyer_id', $buyer->getKey())
            ->whereState('status', PaidState::class)
            ->exists();
    }
}
