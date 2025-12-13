<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_conditions', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_conditions', 'iccid')) {
                $table->string('iccid')->nullable()->index()->after('simcard_id')->comment('ICCID limpio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_conditions', function (Blueprint $table) {
            if (Schema::hasColumn('sales_conditions', 'iccid')) {
                $table->dropColumn('iccid');
            }
        });
    }
};
