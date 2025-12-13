<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_conditions', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_conditions', 'population')) {
                $table->string('population', 100)
                    ->nullable()
                    ->after('commission_percentage');
            }

            if (! Schema::hasColumn('sales_conditions', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('import_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_conditions', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_conditions', 'created_by')) {
                try {
                    $table->dropForeign(['created_by']);
                } catch (\Throwable $e) {
                    // Ignorar si la foreign key no existe.
                }

                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('sales_conditions', 'population')) {
                $table->dropColumn('population');
            }
        });
    }
};

