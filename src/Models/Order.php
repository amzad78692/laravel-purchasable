<?php

namespace Amzad\LaravelPurchasable\Models;

use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Exceptions\InvalidTransitionException;
use Amzad\LaravelPurchasable\States\Order\CancelledState;
use Amzad\LaravelPurchasable\States\Order\FailedState;
use Amzad\LaravelPurchasable\States\Order\OrderState;
use Amzad\LaravelPurchasable\States\Order\PaidState;
use Amzad\LaravelPurchasable\States\Order\PartiallyRefundedState;
use Amzad\LaravelPurchasable\States\Order\PendingState;
use Amzad\LaravelPurchasable\States\Order\RefundedState;
use Amzad\LaravelPurchasable\Support\ResolvesDisplayName;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;
use Spatie\ModelStates\HasStates;

/**
 * @property int $id
 * @property string $order_number
 * @property string|null $buyer_type null for guest orders — see isGuest()
 * @property int|null $buyer_id null for guest orders — see isGuest()
 * @property string|null $guest_email
 * @property string|null $guest_name
 * @property string|null $guest_phone
 * @property string|null $access_token
 * @property string|null $purchasable_type null for multi-item orders — see items()
 * @property int|null $purchasable_id null for multi-item orders — see items()
 * @property int|null $quantity null for multi-item orders — see items()
 * @property OrderState $status
 * @property string $amount
 * @property string $currency
 * @property Carbon|null $paid_at
 * @property array|null $meta
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Transaction> $transactions
 * @property-read Collection<int, OrderItem> $items
 */
class Order extends Model
{
    use HasFactory;
    use HasStates;
    use ResolvesDisplayName;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['access_token'];

    public function __construct(array $attributes = [])
    {
        $this->table = config('purchasable.tables.orders', 'orders');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => OrderState::class,
            'amount' => 'decimal:3',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function buyer(): MorphTo
    {
        return $this->morphTo();
    }

    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Line items for a multi-item order (see CreateMultiItemOrder /
     * Buyer::purchaseMultiple()). Empty for single-item orders, which store
     * their purchasable directly on the order via purchasable_type/id.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isMultiItem(): bool
    {
        return $this->purchasable_type === null;
    }

    /**
     * Falls back to "ClassName #id" when the buyer model doesn't implement
     * the optional getBuyerDisplayName() method. Guest orders show the
     * guest's name, or their email when no name was given.
     */
    public function buyerDisplayName(): string
    {
        if ($this->isGuest()) {
            return $this->guest_name ?: ($this->guest_email ?: __('Guest'));
        }

        return $this->resolveDisplayName($this->buyer_type, $this->buyer_id, fn () => $this->buyer, 'getBuyerDisplayName');
    }

    /**
     * Only meaningful for single-item orders — purchasable_type/id are null
     * on a multi-item order, see OrderItem::purchasableDisplayName() for
     * those. Falls back to "ClassName #id" when the purchasable model
     * doesn't implement the optional getPurchasableDisplayName() method.
     */
    public function purchasableDisplayName(): string
    {
        return $this->resolveDisplayName($this->purchasable_type, $this->purchasable_id, fn () => $this->purchasable, 'getPurchasableDisplayName');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->whereState('status', PaidState::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereState('status', PendingState::class);
    }

    public function scopeGuest(Builder $query): Builder
    {
        return $query->whereNull('buyer_id');
    }

    /**
     * Orders placed with this guest email, including ones since claimed by a
     * buyer, so a guest can't sidestep the repurchase check by registering.
     */
    public function scopeForGuest(Builder $query, string $email): Builder
    {
        return $query->where('guest_email', static::normalizeGuestEmail($email));
    }

    public function scopePaidBetween(Builder $query, DateTimeInterface $from, DateTimeInterface $to): Builder
    {
        return $query->whereState('status', PaidState::class)->whereBetween('paid_at', [$from, $to]);
    }

    public static function findByAccessToken(string $token): ?static
    {
        if ($token === '') {
            return null;
        }

        return static::query()->where('access_token', $token)->first();
    }

    public static function normalizeGuestEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    public function isGuest(): bool
    {
        return $this->buyer_id === null;
    }

    /**
     * The guest email, or the buyer's `email` attribute when it has one.
     */
    public function buyerEmail(): ?string
    {
        if ($this->isGuest()) {
            return $this->guest_email;
        }

        return $this->buyer?->getAttribute('email') ?? $this->guest_email;
    }

    public function isPaid(): bool
    {
        return $this->status->equals(PaidState::class);
    }

    /**
     * @throws InvalidTransitionException
     */
    public function transitionStatusTo(string $stateClass): static
    {
        try {
            $this->status->transitionTo($stateClass);

            return $this;
        } catch (CouldNotPerformTransition $exception) {
            throw InvalidTransitionException::from(
                $this->status::getMorphClass(),
                $stateClass::getMorphClass(),
                $exception,
            );
        }
    }

    public function markAsPaid(): static
    {
        return $this->transitionStatusTo(PaidState::class);
    }

    public function markAsFailed(): static
    {
        return $this->transitionStatusTo(FailedState::class);
    }

    public function cancel(): static
    {
        return $this->transitionStatusTo(CancelledState::class);
    }

    public function markAsRefunded(): static
    {
        return $this->transitionStatusTo(RefundedState::class);
    }

    public function markAsPartiallyRefunded(): static
    {
        return $this->transitionStatusTo(PartiallyRefundedState::class);
    }

    /**
     * Failed -> Pending, so a host can retry payment on the same order
     * instead of creating a new one (see allow_multiple_pending_orders).
     */
    public function retry(): static
    {
        return $this->transitionStatusTo(PendingState::class);
    }

    public function refundedAmount(): string
    {
        $sum = $this->transactions()
            ->where('type', TransactionType::Refund)
            ->where('status', TransactionStatus::Success)
            ->sum('amount');

        return number_format((float) $sum, 3, '.', '');
    }

    public function isFullyRefunded(): bool
    {
        return bccomp($this->refundedAmount(), (string) $this->amount, 3) === 0;
    }

    public function isPartiallyRefunded(): bool
    {
        return bccomp($this->refundedAmount(), '0', 3) > 0 && ! $this->isFullyRefunded();
    }

    public static function revenueBetween(DateTimeInterface $from, DateTimeInterface $to): string
    {
        $sum = static::query()->paidBetween($from, $to)->sum('amount');

        return number_format((float) $sum, 3, '.', '');
    }

    /**
     * @return array<string, int>
     */
    public static function countsByStatus(): array
    {
        return static::query()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();
    }
}
