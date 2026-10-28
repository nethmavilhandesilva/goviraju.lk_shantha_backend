<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Loan manager support for:
     *  - Farmer loans (loan_type farmer_old / farmer_today), stored in income_expenses
     *    like customer loans and linked to the farmer by supplier_code.
     *  - Typed-in customer/farmer names that are not in the customers/suppliers tables:
     *    such loans have no customer_id, so customer_id must allow NULL.
     *
     * income_expenses and customers_loans were created outside migrations, so every
     * change is guarded and only applied when it is still needed.
     */
    public function up()
    {
        if (Schema::hasTable('income_expenses') && ! Schema::hasColumn('income_expenses', 'supplier_code')) {
            Schema::table('income_expenses', function (Blueprint $table) {
                $table->string('supplier_code')->nullable()->index();
            });
        }

        $this->makeColumnNullable('income_expenses', 'customer_id');
        $this->makeColumnNullable('customers_loans', 'customer_id');
        $this->makeColumnNullable('customers_loans', 'customer_short_name');
    }

    public function down()
    {
        if (Schema::hasTable('income_expenses') && Schema::hasColumn('income_expenses', 'supplier_code')) {
            Schema::table('income_expenses', function (Blueprint $table) {
                $table->dropIndex(['supplier_code']);
                $table->dropColumn('supplier_code');
            });
        }
        // customer_id / customer_short_name are left nullable: rows saved with a
        // typed-in name would block switching them back to NOT NULL.
    }

    /**
     * Switch an existing MySQL column to NULL-able, keeping its current type.
     */
    private function makeColumnNullable(string $table, string $column): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $info = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        if ($info && $info->IS_NULLABLE === 'NO') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$info->COLUMN_TYPE} NULL");
        }
    }
};
