<?php

use Amzad\LaravelPurchasable\Models\Order;
use Amzad\LaravelPurchasable\Models\OrderItem;
use Amzad\LaravelPurchasable\Models\Transaction;

return [

    'tables' => [
        'orders' => 'orders',
        'transactions' => 'transactions',
        'order_items' => 'order_items',
    ],

    'models' => [
        'order' => Order::class,
        'transaction' => Transaction::class,
        'order_item' => OrderItem::class,
    ],

    'order_number' => [
        'format' => 'ORD-{year}-{sequence:5}',
    ],

    'transaction_number' => [
        'format' => 'TXN-{year}-{sequence:6}',
    ],

    'default_currency' => 'KWD',

    /*
     * Maps a {gateway} route segment on the package's webhook endpoint
     * (POST /purchasable/webhooks/{gateway}) to a WebhookHandlerContract
     * implementation. Empty by default — host app registers its own per
     * gateway, e.g. 'stripe' => \App\Webhooks\StripeWebhookHandler::class.
     * Signature verification is the handler's responsibility, not the
     * package's.
     */
    'webhook_handlers' => [
        //
    ],

    /*
     * If false, a buyer cannot create a second pending order for the same
     * purchasable while one is already pending.
     */
    'allow_multiple_pending_orders' => false,

    /*
     * If false, a buyer who already has a paid order for a purchasable
     * cannot purchase it again (e.g. one-time event tickets). Set to true
     * for purchasables that are legitimately buyable more than once by the
     * same buyer (e.g. a recurring subscription renewal, a top-up).
     */
    'allow_repurchase' => false,

    /*
     * Minutes after which a pending order is considered abandoned.
     * Null disables automatic expiry. When set, schedule
     * `purchasable:expire-orders` in the host app to act on it.
     */
    'pending_order_ttl' => null,

    /*
     * Multi-item ("cart-style") orders, created via CreateMultiItemOrder /
     * Buyer::purchaseMultiple(). By default, allow_repurchase and
     * allow_multiple_pending_orders are NOT enforced for multi-item orders,
     * since generic ecommerce carts routinely contain repeat or concurrent
     * purchases of the same item (unlike single-item tickets/subscriptions).
     * Set this to true to re-apply those same per-item guards to each item
     * in a multi-item order.
     */
    'multi_item' => [
        'enforce_purchase_guards' => false,
    ],

];
