<?php

use App\Database\SafeSchemaBuilder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('migrate:safe', function () {
    Schema::swap(new SafeSchemaBuilder(DB::connection()));

    return $this->call('migrate', ['--force' => true]);
})->purpose('Run migrations, skipping tables and columns that already exist');
