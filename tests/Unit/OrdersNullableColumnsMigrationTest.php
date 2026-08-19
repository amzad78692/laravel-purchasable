<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('allows purchasable_type, purchasable_id, and quantity to be null on the orders table', function () {
    $id = DB::table('orders')->insertGetId([
        'order_number' => 'ORD-TEST-NULLABLE',
        'buyer_type' => 'App\\Models\\User',
        'buyer_id' => 1,
        'purchasable_type' => null,
        'purchasable_id' => null,
        'quantity' => null,
        'status' => 'pending',
        'amount' => '0.000',
        'currency' => 'KWD',
        'meta' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('orders')->where('id', $id)->first();

    expect($row->purchasable_type)->toBeNull()
        ->and($row->purchasable_id)->toBeNull()
        ->and($row->quantity)->toBeNull();
});

it('preserves the orders table indexes after the SQLite nullable-column rebuild', function () {
    $indexes = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'orders'"))
        ->pluck('name');

    expect($indexes)->toContain('orders_order_number_unique')
        ->and($indexes)->toContain('orders_buyer_type_buyer_id_index')
        ->and($indexes)->toContain('orders_purchasable_type_purchasable_id_index')
        ->and($indexes)->toContain('orders_status_index');
});

it('still enforces the order_number unique constraint after the rebuild', function () {
    DB::table('orders')->insert([
        'order_number' => 'ORD-TEST-UNIQUE',
        'buyer_type' => 'App\\Models\\User',
        'buyer_id' => 1,
        'purchasable_type' => 'App\\Models\\Product',
        'purchasable_id' => 1,
        'quantity' => 1,
        'status' => 'pending',
        'amount' => '10.000',
        'currency' => 'KWD',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('orders')->insert([
        'order_number' => 'ORD-TEST-UNIQUE',
        'buyer_type' => 'App\\Models\\User',
        'buyer_id' => 2,
        'purchasable_type' => 'App\\Models\\Product',
        'purchasable_id' => 2,
        'quantity' => 1,
        'status' => 'pending',
        'amount' => '10.000',
        'currency' => 'KWD',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
