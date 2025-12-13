<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('imports')) {
            return;
        }

        DB::statement('ALTER TABLE imports DROP CONSTRAINT IF EXISTS imports_type_period_cutoff_unique');

        DB::statement("
            CREATE UNIQUE INDEX imports_type_period_cutoff_unique
            ON imports (type, period, cutoff_number)
            WHERE status != 'failed'
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('imports')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS imports_type_period_cutoff_unique');

        Schema::table('imports', function (Blueprint $table) {
            if (Schema::hasColumn('imports', 'period')) {
                $table->unique(['type', 'period', 'cutoff_number'], 'imports_type_period_cutoff_unique');
            }
        });
    }
};
