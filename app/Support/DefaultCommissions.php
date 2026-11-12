<?php

namespace App\Support;

/**
 * Built-in commission bands (commission per kg by sale price per kg).
 *
 * These are the default general commissions and are applied even when the
 * commissions table is empty or unreachable. The commissions table is only a
 * secondary source: item/supplier specific rules there still override these,
 * and its general rules are used only for prices these bands do not cover.
 *
 * Each band is [starting_price, end_price, commission_amount]; a null end
 * price means "and above".
 */
class DefaultCommissions
{
    public const BANDS = [
        [1.00,   9.00,   0.50],
        [10.00,  19.00,  1.00],
        [20.00,  29.00,  1.50],
        [30.00,  44.00,  2.00],
        [45.00,  59.00,  2.50],
        [60.00,  79.00,  3.00],
        [80.00,  99.00,  4.00],
        [100.00, 124.00, 5.00],
        [125.00, 149.00, 6.00],
        [150.00, 199.00, 7.50],
        [200.00, 249.00, 10.00],
        [250.00, 299.00, 12.50],
        [300.00, 399.00, 5.00],
        [400.00, 499.00, 10.00],
        [500.00, 599.00, 15.00],
        [600.00, 699.00, 20.00],
        [700.00, 799.00, 25.00],
        [800.00, 899.00, 30.00],
        [900.00, 999.00, 35.00],
        [1000.00, null,  50.00],
    ];

    /**
     * Commission per kg for a sale price, or null when no band covers it.
     * A price between two bands (e.g. 9.50 between 1-9 and 10-19) uses the lower band.
     */
    public static function forPrice(float $pricePerKg): ?float
    {
        if ($pricePerKg <= 0) {
            return null;
        }

        $match = null;
        foreach (self::BANDS as [$start, $end, $amount]) {
            if ($start > $pricePerKg) {
                continue;
            }
            if ($end !== null && $end < floor($pricePerKg)) {
                continue;
            }
            if ($match === null || $start > $match[0]) {
                $match = [$start, $amount];
            }
        }

        return $match === null ? null : (float) $match[1];
    }
}
