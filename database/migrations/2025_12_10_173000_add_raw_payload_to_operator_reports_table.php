<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('operator_reports', 'raw_payload')) {
                $table->jsonb('raw_payload')->nullable()->after('amount_difference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            if (Schema::hasColumn('operator_reports', 'raw_payload')) {
                $table->dropColumn('raw_payload');
            }
        });
    }
};
