<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_conditions', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_conditions', 'phone_number')) {
                $table->string('phone_number')->nullable()->index()->after('iccid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_conditions', function (Blueprint $table) {
            if (Schema::hasColumn('sales_conditions', 'phone_number')) {
                $table->dropColumn('phone_number');
            }
        });
    }
};
