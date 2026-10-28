<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Second login password per user (stored hashed, like `password`).
     * Users with a second password must enter both passwords to log in.
     */
    public function up()
    {
        if (! Schema::hasColumn('users', 'password2')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('password2')->nullable()->after('password');
            });
        }

        // Second password for the existing `pos12345` account (bcrypt hash only,
        // never the plain password). Leaves it alone if one is already set.
        DB::table('users')
            ->where('user_id', 'pos12345')
            ->whereNull('password2')
            ->update(['password2' => '$2y$12$7zNNK9yf.C/nhji49CMpJurY3hYMvnSzjfsN7PCcdTyun2R3BJj3C']);
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'password2')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('password2');
            });
        }
    }
};
