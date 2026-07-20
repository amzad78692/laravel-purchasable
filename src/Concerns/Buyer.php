<?php

namespace Amzad\LaravelPurchasable\Concerns;

use Amzad\LaravelPurchasable\Actions\CreateOrder;
use Amzad\LaravelPurchasable\Contracts\Purchasable as PurchasableContract;
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
     * Paid orders, most recent first — a ready-made "purchase history" list.
     *
     * @return MorphMany<Order, $this>
     */
    public function purchaseHistory(): MorphMany
    {
        return $this->orders()->whereState('status', PaidState::class)->latest('paid_at');
    }
}
