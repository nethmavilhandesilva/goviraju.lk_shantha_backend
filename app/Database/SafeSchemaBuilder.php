<?php

namespace App\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\MySqlBuilder;

/**
 * Schema builder used by `php artisan migrate:safe`.
 *
 * Some tables on the live database were created outside migrations, so a plain
 * `migrate` can fail with "table already exists" or "duplicate column". This
 * builder makes migrations tolerant of that:
 *  - Schema::create() on a table that already exists only adds the missing columns.
 *  - Schema::table() skips adding columns that already exist.
 * Indexes and foreign keys on skipped columns are skipped too, since they most
 * likely exist already. Everything else (drops, changes, raw SQL) runs as written.
 */
class SafeSchemaBuilder extends MySqlBuilder
{
    private const INDEX_COMMANDS = ['primary', 'unique', 'index', 'fulltext', 'spatialIndex', 'foreign'];

    protected function build(Blueprint $blueprint)
    {
        $table = $blueprint->getTable();

        if (! $this->hasTable($table)) {
            parent::build($blueprint);

            return;
        }

        $existing = array_map('strtolower', $this->getColumnListing($table));

        $skipped = [];
        foreach ($blueprint->getAddedColumns() as $column) {
            if (! $column->change && in_array(strtolower($column->name), $existing, true)) {
                $skipped[] = strtolower($column->name);
                $blueprint->removeColumn($column->name);
            }
        }

        $creating = $blueprint->creating();

        $commands = array_values(array_filter($blueprint->getCommands(), function ($command) use ($skipped) {
            // The table exists, so turn a create into an alter
            if ($command->name === 'create') {
                return false;
            }

            if (in_array($command->name, self::INDEX_COMMANDS, true) && is_array($command->columns ?? null)) {
                foreach ($command->columns as $column) {
                    if (in_array(strtolower($column), $skipped, true)) {
                        return false;
                    }
                }
            }

            return true;
        }));

        // Columns of a create are not queued as commands; queue them so they are added
        // (before any index on them) now that the blueprint is an alter
        if ($creating) {
            $commands = array_merge($blueprint->getAddedColumns(), $commands);
        }

        (fn () => $this->commands = $commands)->call($blueprint);

        if ($creating) {
            $added = array_map(fn ($column) => $column->name, $blueprint->getAddedColumns());
            echo "  Table `{$table}` already exists"
                .($added ? ', adding missing columns: '.implode(', ', $added) : ', nothing to add')."\n";
        }

        if ($blueprint->getAddedColumns() || $commands) {
            parent::build($blueprint);
        }
    }
}
