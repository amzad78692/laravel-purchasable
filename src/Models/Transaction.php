<?php

namespace Amzad\LaravelPurchasable\Models;

use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $transaction_number
 * @property int $order_id
 * @property TransactionType $type
 * @property string $gateway
 * @property string|null $gateway_transaction_id
 * @property TransactionStatus $status
 * @property string $amount
 * @property string $currency
 * @property array|null $response_payload
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Order $order
 */
class Transaction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function __construct(array $attributes = [])
    {
        $this->table = config('purchasable.tables.transactions', 'transactions');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'amount' => 'decimal:3',
            'response_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
