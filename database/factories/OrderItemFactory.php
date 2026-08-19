<?php

namespace Amzad\LaravelPurchasable\Database\Factories;

use Amzad\LaravelPurchasable\Enums\OrderItemStatus;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = fake()->randomFloat(3, 5, 500);

        return [
            'order_id' => Order::factory(),
            'purchasable_type' => 'App\\Models\\Product',
            'purchasable_id' => fake()->numberBetween(1, 1000),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => bcmul((string) $unitPrice, (string) $quantity, 3),
            'currency' => 'KWD',
            'status' => OrderItemStatus::Active,
            'meta' => [],
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => OrderItemStatus::Cancelled]);
    }
}
