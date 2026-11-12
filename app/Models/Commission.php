<?php

namespace App\Models;

use App\Support\DefaultCommissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_code',
        'item_name',
        'starting_price',
        'end_price',
        'commission_amount',
        'supplier_code',
        'supplier_name',
        'type',
    ];

    /**
     * Commission per kg for a sale price.
     *
     * Lookup order:
     *   1. commissions table rule for this item code (price within its range)
     *   2. commissions table rule for this supplier code (price within its range)
     *   3. built-in default bands (App\Support\DefaultCommissions)
     *
     * Rows in the commissions table with neither an item code nor a supplier code are
     * not used; the built-in bands are the only general commissions. Table rules are
     * told apart by which codes are filled in (not the type column), a price between
     * two bands (e.g. 9.50 between 1-9 and 10-19) uses the lower band, and an empty
     * or 0 end price means "and above". If the table cannot be read the built-in
     * bands are still used.
     */
    public static function amountFor($itemCode, $supplierCode, $pricePerKg): float
    {
        $price = (float) $pricePerKg;
        if ($price <= 0) {
            return 0.00;
        }

        $blank = fn ($column) => fn ($q) => $q->whereNull($column)->orWhere($column, '');

        $itemScope = fn ($q) => $q->where('item_code', trim((string) $itemCode));
        $supplierScope = fn ($q) => $q->where('supplier_code', trim((string) $supplierCode))->where($blank('item_code'));

        foreach ([$itemScope, $supplierScope] as $scope) {
            $amount = static::fromTable($scope, $price);
            if ($amount !== null) {
                return $amount;
            }
        }

        return DefaultCommissions::forPrice($price) ?? 0.00;
    }

    /**
     * Commission from the first matching commissions-table rule within the scope,
     * or null when nothing matches (or the table cannot be read).
     */
    private static function fromTable(callable $scope, float $price): ?float
    {
        // The live table stores prices as text, so compare as numbers explicitly:
        // as text "300" <= "35" is true and 300-399 would match a price of 35.
        $start = 'CAST(`starting_price` AS DECIMAL(12,2))';
        $end = 'CAST(`end_price` AS DECIMAL(12,2))';

        $inBand = function ($query) use ($price, $start, $end) {
            $query->where(function ($q) use ($price, $start) {
                $q->whereNull('starting_price')->orWhere('starting_price', '')
                    ->orWhereRaw("$start <= ?", [$price]);
            })->where(function ($q) use ($price, $start, $end) {
                $q->whereNull('end_price')->orWhere('end_price', '')
                    ->orWhereRaw("$end <= 0")
                    ->orWhereRaw("$end < $start")
                    ->orWhereRaw("$end >= ?", [floor($price)]);
            });
        };

        try {
            $rule = static::query()
                ->tap($scope)
                ->where($inBand)
                ->orderByRaw("$start DESC")
                ->first();
        } catch (\Throwable $e) {
            Log::warning('Commission table lookup failed, using built-in bands only: ' . $e->getMessage());
            return null;
        }

        return $rule ? (float) $rule->commission_amount : null;
    }
}
