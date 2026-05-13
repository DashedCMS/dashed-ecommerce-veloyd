<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVeloydOrderTableFields extends Migration
{
    public function up()
    {
        Schema::table('dashed__order_veloyd', function (Blueprint $table) {
            $table->string('carrier')
                ->after('label_printed')
                ->nullable();
            $table->string('package_type')
                ->after('carrier')
                ->nullable();
            $table->string('delivery_type')
                ->after('package_type')
                ->nullable();
            $table->string('error')
                ->after('delivery_type')
                ->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dashed__order_veloyd');
    }
}
