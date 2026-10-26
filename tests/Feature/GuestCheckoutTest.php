<?php

use Amzad\LaravelPurchasable\Actions\CreateMultiItemOrder;
use Amzad\LaravelPurchasable\Actions\RecordTransaction;
use Amzad\LaravelPurchasable\Enums\TransactionStatus;
use Amzad\LaravelPurchasable\Enums\TransactionType;
use Amzad\LaravelPurchasable\Events\OrderClaimed;
use Amzad\LaravelPurchasable\Events\OrderCreated;
use Amzad\LaravelPurchasable\Events\OrderPaid;
use Amzad\LaravelPurchasable\Exceptions\AlreadyPurchasedException;
use Amzad\LaravelPurchasable\Exceptions\GuestCheckoutException;
use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestBuyer;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestPurchasable;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config()->set('purchasable.allow_guest_checkout', true);
});

function payGuestOrder(Order $order): Order
{
    app(RecordTransaction::class)->execute(
        order: $order,
        type: TransactionType::Charge,
        gateway: 'manual',
        amount: $order->amount,
        status: TransactionStatus::Success,
    );

    return $order->refresh();
}

it('creates a guest order without a buyer', function () {
    Event::fake([OrderCreated::class]);

    $purchasable = TestPurchasable::create(['price' => '12.500']);

    $order = $purchasable->purchaseAsGuest([
        'email' => '  Guest@Example.COM ',
        'name' => 'Sam Guest',
        'phone' => '+96550000000',
    ], ['quantity' => 2]);

    expect($order->isGuest())->toBeTrue()
        ->and($order->buyer_type)->toBeNull()
        ->and($order->buyer_id)->toBeNull()
        ->and($order->buyer)->toBeNull()
        ->and($order->guest_email)->toBe('guest@example.com')
        ->and($order->guest_name)->toBe('Sam Guest')
        ->and($order->guest_phone)->toBe('+96550000000')
        ->and($order->buyerEmail())->toBe('guest@example.com')
        ->and($order->amount)->toBe('25.000')
        ->and($order->access_token)->toHaveLength(64);

    Event::assertDispatched(fn (OrderCreated $event) => $event->order->is($order));
});

it('refuses guest checkout while allow_guest_checkout is disabled', function () {
    config()->set('purchasable.allow_guest_checkout', false);

    $purchasable = TestPurchasable::create(['price' => '10.000']);

    expect(fn () => $purchasable->purchaseAsGuest(['email' => 'guest@example.com']))
        ->toThrow(GuestCheckoutException::class);

    expect(Order::count())->toBe(0);
});

it('requires a valid guest email', function (array $guest) {
    $purchasable = TestPurchasable::create(['price' => '10.000']);

    expect(fn () => $purchasable->purchaseAsGuest($guest))
        ->toThrow(GuestCheckoutException::class);
})->with([
    'missing' => [[]],
    'blank' => [['email' => '   ']],
    'malformed' => [['email' => 'not-an-email']],
]);

it('refuses a second guest order while one is pending, matching email case-insensitively', function () {
    $purchasable = TestPurchasable::create(['price' => '10.000']);

    $purchasable->purchaseAsGuest(['email' => 'guest@example.com']);

    expect(fn () => $purchasable->purchaseAsGuest(['email' => 'GUEST@example.com']))
        ->toThrow(AlreadyPurchasedException::class);
});

it('refuses a guest repurchase of an already-paid item', function () {
    $purchasable = TestPurchasable::create(['price' => '10.000']);

    payGuestOrder($purchasable->purchaseAsGuest(['email' => 'guest@example.com']));

    expect($purchasable->isPurchasedByGuest('Guest@example.com'))->toBeTrue()
        ->and(fn () => $purchasable->purchaseAsGuest(['email' => 'guest@example.com']))
        ->toThrow(AlreadyPurchasedException::class);
});

it('honours allow_multiple_pending_orders and allow_repurchase for guests', function () {
    config()->set('purchasable.allow_multiple_pending_orders', true);
    config()->set('purchasable.allow_repurchase', true);

    $purchasable = TestPurchasable::create(['price' => '10.000']);

    payGuestOrder($purchasable->purchaseAsGuest(['email' => 'guest@example.com']));
    $purchasable->purchaseAsGuest(['email' => 'guest@example.com']);
    $purchasable->purchaseAsGuest(['email' => 'guest@example.com']);

    expect(Order::query()->forGuest('guest@example.com')->count())->toBe(3);
});

it('keeps different guests independent', function () {
    $purchasable = TestPurchasable::create(['price' => '10.000']);

    $purchasable->purchaseAsGuest(['email' => 'one@example.com']);
    $purchasable->purchaseAsGuest(['email' => 'two@example.com']);

    expect(Order::query()->guest()->count())->toBe(2);
});

it('runs a guest order through the normal payment flow', function () {
    Event::fake([OrderPaid::class]);

    $purchasable = TestPurchasable::create(['price' => '10.000']);
    $order = payGuestOrder($purchasable->purchaseAsGuest(['email' => 'guest@example.com']));

    expect($order->isPaid())->toBeTrue()
        ->and($order->transactions()->count())->toBe(1);

    Event::assertDispatched(OrderPaid::class);
});

it('finds a guest order by its access token and hides the token from serialization', function () {
    $purchasable = TestPurchasable::create(['price' => '10.000']);
    $order = $purchasable->purchaseAsGuest(['email' => 'guest@example.com']);

    expect(Order::findByAccessToken($order->access_token)?->is($order))->toBeTrue()
        ->and(Order::findByAccessToken('wrong-token'))->toBeNull()
        ->and(Order::findByAccessToken(''))->toBeNull()
        ->and($order->toArray())->not->toHaveKey('access_token');
});

it('does not give buyer orders an access token', function () {
    $buyer = TestBuyer::create();
    $order = $buyer->purchase(TestPurchasable::create(['price' => '10.000']));

    expect($order->isGuest())->toBeFalse()
        ->and($order->access_token)->toBeNull();
});

it('lets a buyer claim guest orders placed with their email', function () {
    Event::fake([OrderClaimed::class]);

    $purchasable = TestPurchasable::create(['price' => '10.000']);
    $other = TestPurchasable::create(['price' => '5.000']);

    $first = $purchasable->purchaseAsGuest(['email' => 'guest@example.com']);
    $second = $other->purchaseAsGuest(['email' => 'guest@example.com']);
    $someoneElse = $purchasable->purchaseAsGuest(['email' => 'other@example.com']);

    $buyer = TestBuyer::create(['email' => 'Guest@Example.com']);

    expect($buyer->claimGuestOrders())->toBe(2)
        ->and($buyer->orders()->pluck('id')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and($first->refresh()->isGuest())->toBeFalse()
        ->and($first->access_token)->toBeNull()
        ->and($first->guest_email)->toBe('guest@example.com')
        ->and($someoneElse->refresh()->isGuest())->toBeTrue()
        ->and($buyer->hasPendingOrderFor($purchasable))->toBeTrue();

    Event::assertDispatchedTimes(OrderClaimed::class, 2);

    expect($buyer->claimGuestOrders())->toBe(0);
});

it('claims by an explicit email and does nothing without one', function () {
    $purchasable = TestPurchasable::create(['price' => '10.000']);
    $purchasable->purchaseAsGuest(['email' => 'guest@example.com']);

    $buyer = TestBuyer::create();

    expect($buyer->claimGuestOrders())->toBe(0)
        ->and($buyer->claimGuestOrders('guest@example.com'))->toBe(1);
});

it('still blocks a guest repurchase after the paid order was claimed', function () {
    $purchasable = TestPurchasable::create(['price' => '10.000']);
    payGuestOrder($purchasable->purchaseAsGuest(['email' => 'guest@example.com']));

    TestBuyer::create(['email' => 'guest@example.com'])->claimGuestOrders();

    expect(fn () => $purchasable->purchaseAsGuest(['email' => 'guest@example.com']))
        ->toThrow(AlreadyPurchasedException::class);
});

it('creates a multi-item guest order', function () {
    $ticket = TestPurchasable::create(['price' => '10.000']);
    $merch = TestPurchasable::create(['price' => '2.500']);

    $order = app(CreateMultiItemOrder::class)->execute(
        [['purchasable' => $ticket, 'quantity' => 2], ['purchasable' => $merch]],
        null,
        ['guest' => ['email' => 'Guest@example.com', 'name' => 'Sam Guest']],
    );

    expect($order->isGuest())->toBeTrue()
        ->and($order->isMultiItem())->toBeTrue()
        ->and($order->guest_email)->toBe('guest@example.com')
        ->and($order->access_token)->toHaveLength(64)
        ->and($order->amount)->toBe('22.500')
        ->and($order->items()->count())->toBe(2);
});

it('applies guest guards to multi-item orders only when enforce_purchase_guards is on', function () {
    $ticket = TestPurchasable::create(['price' => '10.000']);
    $ticket->purchaseAsGuest(['email' => 'guest@example.com']);

    $create = fn () => app(CreateMultiItemOrder::class)->execute(
        [['purchasable' => $ticket]],
        null,
        ['guest' => ['email' => 'guest@example.com']],
    );

    expect($create())->toBeInstanceOf(Order::class);

    config()->set('purchasable.multi_item.enforce_purchase_guards', true);

    expect($create)->toThrow(AlreadyPurchasedException::class);
});

it('refuses a multi-item guest order while guest checkout is disabled', function () {
    config()->set('purchasable.allow_guest_checkout', false);

    expect(fn () => app(CreateMultiItemOrder::class)->execute(
        [['purchasable' => TestPurchasable::create(['price' => '10.000'])]],
        null,
        ['guest' => ['email' => 'guest@example.com']],
    ))->toThrow(GuestCheckoutException::class);

    expect(Order::count())->toBe(0);
});

it('uses the guest name, then email, as the buyer display name', function () {
    $named = Order::factory()->guest('guest@example.com')->create(['guest_name' => 'Sam Guest']);
    $unnamed = Order::factory()->guest('anon@example.com')->create(['guest_name' => null]);

    expect($named->buyerDisplayName())->toBe('Sam Guest')
        ->and($unnamed->buyerDisplayName())->toBe('anon@example.com');
});
