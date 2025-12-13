<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recharges', function (Blueprint $table) {
            if (!Schema::hasColumn('recharges', 'iccid')) {
                $table->string('iccid')->nullable()->after('simcard_id');
                $table->index('iccid');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recharges', function (Blueprint $table) {
            if (Schema::hasColumn('recharges', 'iccid')) {
                $table->dropIndex(['iccid']);
                $table->dropColumn('iccid');
            }
        });
    }
};
