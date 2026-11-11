<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * On the live database the commissions table's price range and amount columns
     * are text, so range checks compared as strings ("300" <= "35" is true) and a
     * sale at 35 could pick up the 300-399 band. Store them as numbers.
     *
     * The table may have been created outside migrations, so each column is only
     * converted when it is still a text type. Blank or non-numeric values are set
     * to NULL first so the conversion cannot fail.
     */
    private const COLUMNS = ['starting_price', 'end_price', 'commission_amount'];

    public function up()
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('commissions')) {
            return;
        }

        foreach (self::COLUMNS as $column) {
            if (! Schema::hasColumn('commissions', $column)) {
                continue;
            }

            $info = DB::selectOne(
                'SELECT DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['commissions', $column]
            );

            if (! $info || ! in_array(strtolower($info->DATA_TYPE), ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'], true)) {
                continue;
            }

            DB::statement(
                "UPDATE `commissions` SET `{$column}` = NULL
                 WHERE `{$column}` IS NULL OR TRIM(`{$column}`) = ''
                    OR TRIM(`{$column}`) NOT REGEXP '^[0-9]+(\\\\.[0-9]+)?$'"
            );

            DB::statement("ALTER TABLE `commissions` MODIFY `{$column}` DECIMAL(12,2) NULL");
        }
    }

    public function down()
    {
        // Left as decimals; converting back to text would reintroduce string comparisons.
    }
};
