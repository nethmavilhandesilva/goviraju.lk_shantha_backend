<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    // If your table name is not "sales", uncomment and change this
    // protected $table = 'sales';

    // Columns you want to be mass assignable
    protected $fillable = [

        'customer_name',
        'id',
        'customer_code',
        'supplier_code',
        'code',
        'item_code',
        'item_name',
        'weight',
        'price_per_kg',
        'total',
        'packs',
        'bill_printed', // <-- ADD THIS
        'Processed', 
        'bill_no',
        'updated'   ,
        'is_printed', // <-- ADD THIS
        'CustomerBillEnteredOn',
        'FirstTimeBillPrintedOn',
        'BillChangedOn',
        'BillReprintAfterchanges',
        'UniqueCode',
        'PerKGPrice',
        'PerKGTotal',
        'SellingKGTotal',
        'Date',
        'ip_address',
        'given_amount',
        'commission_amount',
        'CustomerPackCost',
        'CustomerPackLabour',
        'SupplierWeight',
        'SupplierPricePerKg',
        'SupplierTotal',
        'SupplierPackCost',
        'SupplierPackLabour',
        'profit',
        'supplier_bill_printed',
        'supplier_bill_no',
        'breakdown_history',
        'bag_real_weight',
        'credit_transaction',
        'loan_amount',
        'loan_taken',
        'Kuliya',
    ];
    protected $casts = [
    'breakdown_history' => 'array',
     ];

    // If you're using timestamps (created_at and updated_at), keep this
    public $timestamps = true;

    /** Columns that decide which commission rule applies to a sale. */
    public const COMMISSION_INPUTS = ['supplier_code', 'item_code', 'price_per_kg'];

    protected static function booted(): void
    {
        // Whatever path changes the supplier, item or price of a sale (single edit,
        // bulk supplier change, farmer re-assignment on a supplier bill, ...), the
        // commission and the supplier-side figures that depend on it follow along.
        static::saving(function (Sale $sale) {
            if (!$sale->exists || $sale->isDirty(self::COMMISSION_INPUTS)) {
                $sale->applyCommission();
            }
        });
    }

    /**
     * Recompute commission_amount from the commission rules for this sale's
     * supplier, item and price, then the supplier price, supplier total and profit
     * that are derived from it. Does not save.
     */
    public function applyCommission(): static
    {
        $pricePerKg = (float) ($this->price_per_kg ?? 0);
        $commission = Commission::amountFor($this->item_code, $this->supplier_code, $pricePerKg);
        $supplierPricePerKg = abs($pricePerKg - $commission);
        $supplierTotal = (float) ($this->weight ?? 0) * $supplierPricePerKg;

        $this->commission_amount = $commission;
        $this->SupplierPricePerKg = $supplierPricePerKg;
        $this->SupplierTotal = $supplierTotal;
        $this->profit = (float) ($this->total ?? 0) - $supplierTotal;

        return $this;
    }

    /**
     * Re-apply commission to sales that were changed with a mass update (which
     * bypasses model events). Returns how many rows were saved.
     */
    public static function recalculateCommissionFor(array $ids): int
    {
        $count = 0;
        foreach (static::whereIn('id', $ids)->get() as $sale) {
            $sale->applyCommission();
            if ($sale->isDirty()) {
                $sale->save();
                $count++;
            }
        }
        return $count;
    }

    // Relationships (Optional)
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    
   public function item()
{
    return $this->belongsTo(Item::class, 'item_code', 'no');
}
// In Sale.php model
public function itemByNo()
{
    return $this->belongsTo(Item::class, 'item_code', 'no');
}
}
