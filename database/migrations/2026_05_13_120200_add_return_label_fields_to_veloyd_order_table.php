<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__order_veloyd', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__order_veloyd', 'is_return')) {
                $table->boolean('is_return')
                    ->after('error')
                    ->default(false);
            }

            if (! Schema::hasColumn('dashed__order_veloyd', 'is_label_email_sent')) {
                $table->boolean('is_label_email_sent')
                    ->after('is_return')
                    ->default(false);
            }

            if (! Schema::hasColumn('dashed__order_veloyd', 'personal_note')) {
                $table->text('personal_note')
                    ->after('is_label_email_sent')
                    ->nullable();
            }

            if (! Schema::hasColumn('dashed__order_veloyd', 'label_pdf_path')) {
                $table->string('label_pdf_path')
                    ->after('personal_note')
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__order_veloyd', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__order_veloyd', 'label_pdf_path')) {
                $table->dropColumn('label_pdf_path');
            }
            if (Schema::hasColumn('dashed__order_veloyd', 'personal_note')) {
                $table->dropColumn('personal_note');
            }
            if (Schema::hasColumn('dashed__order_veloyd', 'is_label_email_sent')) {
                $table->dropColumn('is_label_email_sent');
            }
            if (Schema::hasColumn('dashed__order_veloyd', 'is_return')) {
                $table->dropColumn('is_return');
            }
        });
    }
};
