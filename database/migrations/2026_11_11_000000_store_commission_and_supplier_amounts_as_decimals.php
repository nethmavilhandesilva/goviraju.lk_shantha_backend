<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commission and the supplier-side amounts derived from it were stored in INT
     * columns, so MySQL rounded them on save: a 0.50 commission became 1, and a
     * supplier price of 49.50 became 50. The same applied to Kuliya (1.50 for
     * bananas) and the GRN per-kg price.
     *
     * These tables were created outside migrations, so each column is only changed
     * when it is still an integer type. Existing values are kept as they are.
     */
    private const DECIMAL_COLUMNS = [
        'sales' => ['commission_amount', 'SupplierPricePerKg', 'SupplierTotal', 'profit', 'Kuliya'],
        'sales_histories' => ['commission_amount', 'SupplierPricePerKg', 'SupplierTotal', 'profit', 'Kuliya'],
        'grn_entries' => ['PerKGPrice'],
    ];

    // Weights match the existing `weight` column, which is DOUBLE.
    private const DOUBLE_COLUMNS = [
        'sales' => ['SupplierWeight'],
        'sales_histories' => ['SupplierWeight'],
    ];

    public function up()
    {
        foreach (self::DECIMAL_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->widenIntegerColumn($table, $column, 'DECIMAL(12,2)');
            }
        }

        foreach (self::DOUBLE_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->widenIntegerColumn($table, $column, 'DOUBLE');
            }
        }
    }

    public function down()
    {
        // Left as decimals: switching back to INT would round stored values again.
    }

    /**
     * Change a MySQL column from an integer type to $type, keeping its NULL-ability.
     * Does nothing when the column is missing or is already a non-integer type.
     */
    private function widenIntegerColumn(string $table, string $column, string $type): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $info = DB::selectOne(
            'SELECT DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        if (! $info || ! in_array(strtolower($info->DATA_TYPE), ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'], true)) {
            return;
        }

        $null = $info->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';
        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type} {$null}");
    }
};
