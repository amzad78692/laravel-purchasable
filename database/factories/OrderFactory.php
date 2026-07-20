<?php

namespace Amzad\LaravelPurchasable\Database\Factories;

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\States\Order\CancelledState;
use Amzad\LaravelPurchasable\States\Order\FailedState;
use Amzad\LaravelPurchasable\States\Order\PaidState;
use Amzad\LaravelPurchasable\States\Order\PendingState;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'buyer_type' => 'App\\Models\\User',
            'buyer_id' => fake()->numberBetween(1, 1000),
            'purchasable_type' => 'App\\Models\\Product',
            'purchasable_id' => fake()->numberBetween(1, 1000),
            'quantity' => 1,
            'status' => PendingState::class,
            'amount' => fake()->randomFloat(3, 5, 500),
            'currency' => 'KWD',
            'meta' => [],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => PendingState::class, 'paid_at' => null]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => PaidState::class, 'paid_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => FailedState::class]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => CancelledState::class]);
    }
}
