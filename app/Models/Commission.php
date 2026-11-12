<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
     * Commission per kg for a sale price, from the commissions table.
     * Item rules win over supplier rules, which win over the general rules.
     * Rules are told apart by which codes are filled in (not the type column), a price
     * between two bands (e.g. 9.50 between 1-9 and 10-19) uses the lower band, and an
     * empty or 0 end price means "and above".
     */
    public static function amountFor($itemCode, $supplierCode, $pricePerKg): float
    {
        $price = (float) $pricePerKg;
        if ($price <= 0) {
            return 0.00;
        }

        $blank = fn ($column) => fn ($q) => $q->whereNull($column)->orWhere($column, '');

        $inBand = function ($query) use ($price) {
            $query->where(function ($q) use ($price) {
                $q->whereNull('starting_price')->orWhere('starting_price', '<=', $price);
            })->where(function ($q) use ($price) {
                $q->whereNull('end_price')
                    ->orWhere('end_price', '<=', 0)
                    ->orWhereColumn('end_price', '<', 'starting_price')
                    ->orWhere('end_price', '>=', floor($price));
            });
        };

        $scopes = [
            fn ($q) => $q->where('item_code', trim((string) $itemCode)),
            fn ($q) => $q->where('supplier_code', trim((string) $supplierCode))->where($blank('item_code')),
            fn ($q) => $q->where($blank('item_code'))->where($blank('supplier_code')),
        ];

        foreach ($scopes as $scope) {
            $rule = static::query()
                ->tap($scope)
                ->where($inBand)
                ->orderByDesc('starting_price')
                ->first();

            if ($rule) {
                return (float) $rule->commission_amount;
            }
        }

        return 0.00;
    }
}
