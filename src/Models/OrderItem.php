<?php

namespace Amzad\LaravelPurchasable\Models;

use Amzad\LaravelPurchasable\Enums\OrderItemStatus;
use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Support\ResolvesDisplayName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A single line item within a multi-item order (see CreateMultiItemOrder /
 * Buyer::purchaseMultiple()). Never used by single-item orders created via
 * CreateOrder / Purchasable::purchase() — those still store their
 * purchasable directly on Order.
 *
 * @property int $id
 * @property int $order_id
 * @property string $purchasable_type
 * @property int $purchasable_id
 * @property int $quantity
 * @property string $unit_price
 * @property string $amount
 * @property string $currency
 * @property OrderItemStatus $status
 * @property array|null $meta
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Order $order
 */
class OrderItem extends Model
{
    use HasFactory;
    use ResolvesDisplayName;

    protected $guarded = ['id'];

    public function __construct(array $attributes = [])
    {
        $this->table = config('purchasable.tables.order_items', 'order_items');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:3',
            'amount' => 'decimal:3',
            'status' => OrderItemStatus::class,
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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

    public function isCancelled(): bool
    {
        return $this->status === OrderItemStatus::Cancelled;
    }

    /**
     * Falls back to "ClassName #id" when the purchasable model doesn't
     * implement the optional getPurchasableDisplayName() method.
     */
    public function purchasableDisplayName(): string
    {
        return $this->resolveDisplayName($this->purchasable_type, $this->purchasable_id, fn () => $this->purchasable, 'getPurchasableDisplayName');
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
}
