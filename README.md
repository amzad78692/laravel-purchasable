# Simple, single-item purchase handling for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/amzad/laravel-purchasable.svg?style=flat-square)](https://packagist.org/packages/amzad/laravel-purchasable)
[![GitHub Tests Action Status](https://github.com/amzad78692/laravel-purchasable/actions/workflows/run-tests.yml/badge.svg)](https://github.com/amzad78692/laravel-purchasable/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/amzad78692/laravel-purchasable/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/amzad78692/laravel-purchasable/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/amzad/laravel-purchasable.svg?style=flat-square)](https://packagist.org/packages/amzad/laravel-purchasable)

Turns any Eloquent model into something purchasable — event bookings, subscription plans, course enrollments — without pulling in a full e-commerce cart system. One order = one purchasable item (with an optional quantity), full stop. No cart, no line items.

See [plan.md](plan.md) for the full design (schema, state machine, events) and delivery phases. This README is filled in incrementally as each phase lands.

## Installation

You can install the package via composer:

```bash
composer require amzad/laravel-purchasable
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="purchasable-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="purchasable-config"
```

## Usage

### Mark a model purchasable

```php
use Amzad\LaravelPurchasable\Concerns\Purchasable;
use Amzad\LaravelPurchasable\Contracts\Purchasable as PurchasableContract;

class Event extends Model implements PurchasableContract
{
    use Purchasable;

    public function getPurchasablePrice(): string|int|float
    {
        return $this->ticket_price;
    }

    // Optional — falls back to config('purchasable.default_currency') if omitted.
    public function getPurchasableCurrency(): string
    {
        return $this->currency;
    }
}
```

### Mark the buying model

```php
use Amzad\LaravelPurchasable\Concerns\Buyer;
use Amzad\LaravelPurchasable\Contracts\Buyer as BuyerContract;

class User extends Authenticatable implements BuyerContract
{
    use Buyer;
}
```

Implementing the `PurchasableContract`/`BuyerContract` interfaces alongside the traits is what gives you IDE and static-analysis support when passing your models into the package's actions — it isn't required at runtime, but it's the supported pattern.

### Create an order

```php
$order = $event->purchase($user, ['quantity' => 2, 'meta' => ['seat_tier' => 'VIP']]);
// or
$order = $user->purchase($event, ['quantity' => 2]);
```

By default, `purchase()` throws `AlreadyPurchasedException` if the buyer already has a paid order for that purchasable, or already has a pending one. Two config flags relax this independently:

```php
// config/purchasable.php
'allow_multiple_pending_orders' => false, // true: buyer can have >1 pending order for the same item at once
'allow_repurchase' => false,              // true: buyer can buy the same item again after already paying for it
```

### Record a transaction / payment

```php
use Amzad\LaravelPurchasable\Actions\RecordTransaction;
use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;

app(RecordTransaction::class)->execute(
    order: $order,
    type: TransactionType::Charge,
    gateway: 'manual',
    amount: $order->amount,
    status: TransactionStatus::Success,
);
// order transitions Pending -> Paid, the OrderPaid event fires
```

A failed charge does **not** move the order out of `Pending` — retries are expected (`failed, failed, success` is a normal sequence). When a host app gives up retrying, it explicitly fails the order:

```php
use Amzad\LaravelPurchasable\Actions\FailOrder;

app(FailOrder::class)->execute($order); // Pending -> Failed, fires OrderFailed
```

A `Failed` order can also be put back into play instead of making the buyer start over:

```php
use Amzad\LaravelPurchasable\Actions\RetryOrder;

app(RetryOrder::class)->execute($order); // Failed -> Pending, fires OrderRetried
```

### Check if a buyer has purchased something

```php
if ($user->hasPurchased($event)) { /* ... */ }
// or
if ($event->isPurchasedBy($user)) { /* ... */ }
```

### Issue a refund

Like charging, the host determines the outcome (however the actual refund gets processed) and passes it in — `RefundOrder` just defaults the amount to the order's remaining unrefunded balance and the `gateway` label to the one used on the original charge:

```php
use Amzad\LaravelPurchasable\Actions\RefundOrder;
use Amzad\LaravelPurchasable\Enums\TransactionStatus;

app(RefundOrder::class)->execute($order, TransactionStatus::Success);                   // full refund -> Refunded
app(RefundOrder::class)->execute($order, TransactionStatus::Success, amount: '10.500'); // partial -> PartiallyRefunded, fires OrderPartiallyRefunded
```

A second, later refund that brings the total up to the full paid amount moves the order from `PartiallyRefunded` to `Refunded` automatically. Pass `gateway`/`gatewayTransactionId`/`responsePayload` explicitly if you need to record something other than the defaults.

### Cancel a pending order

```php
use Amzad\LaravelPurchasable\Actions\CancelOrder;

app(CancelOrder::class)->execute($order); // Pending|Failed -> Cancelled, fires OrderCancelled
```

### Expire abandoned pending orders

Set `pending_order_ttl` (minutes) in `config/purchasable.php`, then schedule the package's Artisan command in your own app — it isn't scheduled automatically, since only the host knows its deployment's scheduler setup:

```php
// routes/console.php (Laravel 11+) or app/Console/Kernel.php
Schedule::command('purchasable:expire-orders')->everyFifteenMinutes();
```

Each run cancels every `Pending` order older than `pending_order_ttl` minutes via `CancelOrder`, so `OrderCancelled` fires normally.

### Receive gateway webhooks

The package exposes a generic, gateway-agnostic endpoint — `POST /purchasable/webhooks/{gateway}` — that does no parsing or signature verification itself. You implement one `WebhookHandlerContract` per gateway and register it:

```php
use Amzad\LaravelPurchasable\Contracts\WebhookHandlerContract;
use Amzad\LaravelPurchasable\Actions\RecordTransaction;
use Illuminate\Http\Request;

class StripeWebhookHandler implements WebhookHandlerContract
{
    public function handle(Request $request): void
    {
        // Verify the Stripe-Signature header yourself, then translate the
        // payload into a RecordTransaction call.
        app(RecordTransaction::class)->execute(/* ... */);
    }
}
```

```php
// config/purchasable.php
'webhook_handlers' => [
    'stripe' => \App\Webhooks\StripeWebhookHandler::class,
],
```

### Reporting helpers

```php
use Amzad\LaravelPurchasable\Models\Order;

Order::revenueBetween(now()->startOfMonth(), now());       // '1250.000'
Order::countsByStatus();                                   // ['pending' => 3, 'paid' => 12, ...]
$user->purchaseHistory()->get();                            // paid orders, newest first
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [amzad78692](https://github.com/amzad78692)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
