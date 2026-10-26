# Changelog

All notable changes to `laravel-purchasable` will be documented in this file.

## v4.0.0 - 2026-10-26

### Added
- Guest checkout: `Purchasable::purchaseAsGuest()` for single-item orders, and a `null` buyer with `$options['guest']` on `CreateMultiItemOrder`, behind the `allow_guest_checkout` config flag (off by default).
- `guest_email`, `guest_name`, `guest_phone` and `access_token` columns on orders, plus the `add_guest_checkout_to_orders_table` upgrade migration.
- `Order::isGuest()`, `buyerEmail()`, `findByAccessToken()`, and the `guest()` and `forGuest()` scopes.
- `Purchasable::isPurchasedByGuest()`, `Buyer::claimGuestOrders()`, the `OrderClaimed` event and `GuestCheckoutException`.
- Filament: guest filter, guest email column, guest details on the order view, and guest columns in the export. `Order::buyerDisplayName()` returns the guest's name or email, so the admin and the invoice's "Billed to" block show the guest.
- `OrderFactory::guest()` state.

### Breaking
- `orders.buyer_type` and `orders.buyer_id` are now nullable.
- The `Buyer` contract now requires `claimGuestOrders()`.
- `CreateOrder::execute()` and `CreateMultiItemOrder::execute()` accept a nullable buyer.

## v3.0.0 - 2026-08-20

### Added

- **Multi-item ("cart-style") orders** — `Buyer::purchaseMultiple()` / `Actions\CreateMultiItemOrder` build a single `Order` from an array of items, mixing different purchasable types freely. New `OrderItem` model (`Order::items()`), `order_items` table, and a nullable `order_item_id` on `transactions`. `RecordTransaction`/`RefundOrder` gained an optional `item` parameter for per-item refunds, independent of the order's own aggregate status. New events `OrderItemRefunded`/`OrderItemPartiallyRefunded`. New config: `multi_item.enforce_purchase_guards`.
- **Friendly buyer/purchasable display names** — optional `getBuyerDisplayName()` / `getPurchasableDisplayName()` methods a host model can implement; used throughout the Filament admin and the PDF invoice instead of raw `ClassName #id`, with that raw form kept as the fallback when unimplemented.
- Filament: a read-only "Items" relation manager on the Order view page for multi-item orders.
- Invoice: a "Billed to" block, a purchasable line for single-item orders, and a line-items table for multi-item orders.

### Changed

- `orders.purchasable_type`, `purchasable_id`, and `quantity` are now nullable (null on multi-item orders). Existing installs need to publish and run the new migrations.

### Fixed

- Invoice table columns (Items, Payment history) no longer drift out of alignment with their headers — locked via `table-layout: fixed` and explicit column widths.

This release is backward compatible — the existing single-item `purchase()`/`CreateOrder` API, schema, events, and Filament behavior are unchanged. See the [README](README.md#multi-item-orders-cart-style-checkout) for upgrade notes (new migrations to run, and a caveat for existing `OrderCreated`/`OrderPaid` listeners that assume a non-null `purchasable`).
