<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * General commission bands (no item or supplier code) for prices of Rs. 300/kg and up:
     * Rs. 5 at 300-399, then Rs. 5 more for every further Rs. 100 up to 900-999 (Rs. 35),
     * and a flat Rs. 50 for any price of Rs. 1000 or more.
     *
     * The commissions table is live data edited from the app, so the bands below 300 are
     * left untouched and only the general rules from 300 upward are replaced.
     */
    private const BANDS = [
        [300,  399,  5],
        [400,  499,  10],
        [500,  599,  15],
        [600,  699,  20],
        [700,  799,  25],
        [800,  899,  30],
        [900,  999,  35],
        [1000, null, 50],
    ];

    public function up()
    {
        if (! Schema::hasTable('commissions')) {
            return;
        }

        DB::transaction(function () {
            $this->generalRulesFrom300()->delete();

            $now = now();
            DB::table('commissions')->insert(array_map(fn ($band) => [
                'item_code' => null,
                'item_name' => null,
                'supplier_code' => null,
                'supplier_name' => null,
                'starting_price' => $band[0],
                'end_price' => $band[1],
                'commission_amount' => $band[2],
                'type' => 'Z',
                'created_at' => $now,
                'updated_at' => $now,
            ], self::BANDS));
        });
    }

    public function down()
    {
        if (! Schema::hasTable('commissions')) {
            return;
        }

        DB::transaction(function () {
            $this->generalRulesFrom300()->delete();

            // The single open-ended band that was in place before this migration
            DB::table('commissions')->insert([
                'item_code' => null,
                'item_name' => null,
                'supplier_code' => null,
                'supplier_name' => null,
                'starting_price' => 300,
                'end_price' => null,
                'commission_amount' => 15,
                'type' => 'Z',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function generalRulesFrom300()
    {
        return DB::table('commissions')
            ->where(fn ($q) => $q->whereNull('item_code')->orWhere('item_code', ''))
            ->where(fn ($q) => $q->whereNull('supplier_code')->orWhere('supplier_code', ''))
            ->where('starting_price', '>=', 300);
    }
};
