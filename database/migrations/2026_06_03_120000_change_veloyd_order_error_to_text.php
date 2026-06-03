<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Veloyd API error messages can exceed the 255-char VARCHAR limit
        // (e.g. full validation/HTML responses), causing "Data too long for
        // column 'error'". Widen to TEXT. SQLite does not enforce string
        // length, so this only matters on MySQL.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasColumn('dashed__order_veloyd', 'error')) {
            return;
        }

        Schema::table('dashed__order_veloyd', function (Blueprint $table) {
            $table->text('error')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasColumn('dashed__order_veloyd', 'error')) {
            return;
        }

        Schema::table('dashed__order_veloyd', function (Blueprint $table) {
            $table->string('error')->nullable()->change();
        });
    }
};
