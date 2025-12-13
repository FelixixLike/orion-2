<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('operator_reports', 'phone_number')) {
            Schema::table('operator_reports', function (Blueprint $table) {
                $table->string('phone_number')->nullable()->after('simcard_id');
                $table->index('phone_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('operator_reports', 'phone_number')) {
            DB::statement('DROP INDEX IF EXISTS operator_reports_phone_number_index');

            Schema::table('operator_reports', function (Blueprint $table) {
                $table->dropColumn('phone_number');
            });
        }
    }
};
