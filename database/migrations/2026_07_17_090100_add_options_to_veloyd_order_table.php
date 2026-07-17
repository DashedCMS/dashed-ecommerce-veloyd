<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_veloyd')) {
            return;
        }

        Schema::table('dashed__order_veloyd', function (Blueprint $table): void {
            if (! Schema::hasColumn('dashed__order_veloyd', 'options')) {
                $table->json('options')->nullable()->after('delivery_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__order_veloyd')) {
            return;
        }

        Schema::table('dashed__order_veloyd', function (Blueprint $table): void {
            if (Schema::hasColumn('dashed__order_veloyd', 'options')) {
                $table->dropColumn('options');
            }
        });
    }
};
