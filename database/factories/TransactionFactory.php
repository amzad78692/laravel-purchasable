<?php

namespace Amzad\LaravelPurchasable\Database\Factories;

use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'transaction_number' => 'TXN-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'order_id' => Order::factory(),
            'type' => TransactionType::Charge,
            'gateway' => 'manual',
            'gateway_transaction_id' => null,
            'status' => TransactionStatus::Success,
            'amount' => fake()->randomFloat(3, 5, 500),
            'currency' => 'KWD',
            'response_payload' => [],
        ];
    }

    public function charge(): static
    {
        return $this->state(fn () => ['type' => TransactionType::Charge]);
    }

    public function refund(): static
    {
        return $this->state(fn () => ['type' => TransactionType::Refund]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => TransactionStatus::Failed, 'paid_at' => null]);
    }
}
