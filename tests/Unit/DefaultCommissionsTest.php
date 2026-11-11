<?php

use App\Support\DefaultCommissions;

test('default commission bands match the agreed price table', function (float $price, ?float $expected) {
    expect(DefaultCommissions::forPrice($price))->toBe($expected);
})->with([
    [0.0, null],
    [0.5, null],
    [1.0, 0.50],
    [9.0, 0.50],
    [9.5, 0.50],   // gap between bands uses the lower band
    [10.0, 1.00],
    [20.0, 1.50],
    [30.0, 2.00],
    [44.9, 2.00],
    [45.0, 2.50],
    [60.0, 3.00],
    [80.0, 4.00],
    [100.0, 5.00],
    [125.0, 6.00],
    [150.0, 7.50],
    [200.0, 10.00],
    [250.0, 12.50],
    [299.99, 12.50],
    [300.0, 15.00],
    [1000.0, 15.00], // open-ended top band
]);
