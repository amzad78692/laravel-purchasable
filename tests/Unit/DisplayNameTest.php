<?php

use Amzad\LaravelPurchasable\Tests\Fixtures\TestBuyer;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestBuyerWithDisplayName;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestPurchasable;
use Amzad\LaravelPurchasable\Tests\Fixtures\TestPurchasableWithDisplayName;

it('falls back to "ClassName #id" for a buyer that has no getBuyerDisplayName()', function () {
    $buyer = TestBuyer::create(['name' => 'Jane Doe']);
    $purchasable = TestPurchasable::create(['price' => '10.000']);

    $order = $buyer->purchase($purchasable);

    expect($order->buyerDisplayName())->toBe('Test Buyer #'.$buyer->id);
});

it('uses getBuyerDisplayName() when the buyer implements it', function () {
    $buyer = TestBuyerWithDisplayName::create(['name' => 'Jane Doe']);
    $purchasable = TestPurchasable::create(['price' => '10.000']);

    $order = $buyer->purchase($purchasable);

    expect($order->buyerDisplayName())->toBe('Jane Doe');
});

it('falls back to "ClassName #id" for a purchasable that has no getPurchasableDisplayName()', function () {
    $buyer = TestBuyer::create();
    $purchasable = TestPurchasable::create(['price' => '10.000', 'name' => 'VIP Ticket']);

    $order = $buyer->purchase($purchasable);

    expect($order->purchasableDisplayName())->toBe('Test Purchasable #'.$purchasable->id);
});

it('uses getPurchasableDisplayName() when the purchasable implements it', function () {
    $buyer = TestBuyer::create();
    $purchasable = TestPurchasableWithDisplayName::create(['price' => '10.000', 'name' => 'VIP Ticket']);

    $order = $buyer->purchase($purchasable);

    expect($order->purchasableDisplayName())->toBe('VIP Ticket');
});

it('resolves each multi-item order item\'s display name independently', function () {
    $buyer = TestBuyer::create();
    $withName = TestPurchasableWithDisplayName::create(['price' => '10.000', 'name' => 'VIP Ticket']);
    $withoutName = TestPurchasable::create(['price' => '5.000']);

    $order = $buyer->purchaseMultiple([
        ['purchasable' => $withName, 'quantity' => 1],
        ['purchasable' => $withoutName, 'quantity' => 1],
    ]);

    $itemWithName = $order->items()->where('purchasable_type', $withName->getMorphClass())->first();
    $itemWithoutName = $order->items()->where('purchasable_type', $withoutName->getMorphClass())->first();

    expect($itemWithName->purchasableDisplayName())->toBe('VIP Ticket')
        ->and($itemWithoutName->purchasableDisplayName())->toBe('Test Purchasable #'.$withoutName->id);
});
