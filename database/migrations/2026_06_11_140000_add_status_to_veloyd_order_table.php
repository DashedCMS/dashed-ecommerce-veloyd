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
            if (! Schema::hasColumn('dashed__order_veloyd', 'status')) {
                $table->string('status')->nullable();
            }
            if (! Schema::hasColumn('dashed__order_veloyd', 'status_updated_at')) {
                $table->timestamp('status_updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__order_veloyd')) {
            return;
        }

        Schema::table('dashed__order_veloyd', function (Blueprint $table): void {
            foreach (['status', 'status_updated_at'] as $column) {
                if (Schema::hasColumn('dashed__order_veloyd', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
