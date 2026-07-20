<?php

use Amzad\LaravelPurchasable\Support\OrderNumberGenerator;

it('formats the year and a zero-padded sequence', function () {
    $generator = new OrderNumberGenerator;

    $number = $generator->generate('order_number');

    expect($number)->toBe('ORD-'.now()->year.'-00001');
});

it('increments the sequence on each call, scoped separately from transaction numbers', function () {
    $generator = new OrderNumberGenerator;

    $first = $generator->generate('order_number');
    $second = $generator->generate('order_number');
    $firstTransaction = $generator->generate('transaction_number');

    expect($first)->toBe('ORD-'.now()->year.'-00001')
        ->and($second)->toBe('ORD-'.now()->year.'-00002')
        ->and($firstTransaction)->toBe('TXN-'.now()->year.'-000001');
});
