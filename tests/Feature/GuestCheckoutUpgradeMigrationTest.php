<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function guestCheckoutUpgradeMigration(): object
{
    return include __DIR__.'/../../database/migrations/add_guest_checkout_to_orders_table.php.stub';
}

beforeEach(function () {
    config()->set('purchasable.tables.orders', 'legacy_orders');

    // The orders table as shipped before guest checkout existed, plus a
    // column a host app added itself, which the SQLite rebuild must keep.
    Schema::create('legacy_orders', function (Blueprint $table) {
        $table->id();
        $table->string('order_number')->unique();
        $table->string('buyer_type');
        $table->unsignedBigInteger('buyer_id');
        $table->string('purchasable_type');
        $table->unsignedBigInteger('purchasable_id');
        $table->unsignedInteger('quantity')->default(1);
        $table->string('status');
        $table->decimal('amount', 10, 3);
        $table->char('currency', 3);
        $table->timestamp('paid_at')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->string('host_custom')->nullable();

        $table->index(['buyer_type', 'buyer_id']);
    });

    DB::table('legacy_orders')->insert([
        'order_number' => 'ORD-2025-00001',
        'buyer_type' => 'App\\Models\\User',
        'buyer_id' => 1,
        'purchasable_type' => 'App\\Models\\Event',
        'purchasable_id' => 1,
        'status' => 'paid',
        'amount' => '10.000',
        'currency' => 'KWD',
        'host_custom' => 'kept',
    ]);
});

it('upgrades a legacy orders table for guest checkout and keeps existing rows', function () {
    guestCheckoutUpgradeMigration()->up();

    expect(Schema::hasColumns('legacy_orders', ['guest_email', 'guest_name', 'guest_phone', 'access_token']))->toBeTrue();

    DB::table('legacy_orders')->insert([
        'order_number' => 'ORD-2025-00002',
        'buyer_type' => null,
        'buyer_id' => null,
        'guest_email' => 'guest@example.com',
        'purchasable_type' => 'App\\Models\\Event',
        'purchasable_id' => 1,
        'status' => 'pending',
        'amount' => '10.000',
        'currency' => 'KWD',
    ]);

    $legacy = DB::table('legacy_orders')->where('order_number', 'ORD-2025-00001')->first();

    expect(DB::table('legacy_orders')->count())->toBe(2)
        ->and($legacy->buyer_id)->toBe(1)
        ->and($legacy->host_custom)->toBe('kept');

    $indexes = collect(Schema::getIndexes('legacy_orders'))->pluck('columns')->all();

    expect($indexes)->toContain(['order_number'], ['buyer_type', 'buyer_id'], ['guest_email'], ['access_token']);

    // The order_number unique index survived the rebuild.
    expect(fn () => DB::table('legacy_orders')->insert([
        'order_number' => 'ORD-2025-00001',
        'purchasable_type' => 'App\\Models\\Event',
        'purchasable_id' => 1,
        'status' => 'pending',
        'amount' => '10.000',
        'currency' => 'KWD',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('is a no-op when run twice and reversible', function () {
    guestCheckoutUpgradeMigration()->up();
    guestCheckoutUpgradeMigration()->up();

    guestCheckoutUpgradeMigration()->down();

    expect(Schema::hasColumn('legacy_orders', 'guest_email'))->toBeFalse()
        ->and(Schema::hasColumn('legacy_orders', 'access_token'))->toBeFalse()
        ->and(DB::table('legacy_orders')->count())->toBe(1);
});

it('skips when the orders table does not exist yet', function () {
    config()->set('purchasable.tables.orders', 'missing_orders');

    guestCheckoutUpgradeMigration()->up();

    expect(Schema::hasTable('missing_orders'))->toBeFalse();
});
