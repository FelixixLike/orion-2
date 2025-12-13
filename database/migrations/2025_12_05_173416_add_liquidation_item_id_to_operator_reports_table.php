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
        Schema::table('operator_reports', function (Blueprint $table) {
            $table->foreignId('liquidation_item_id')
                ->nullable()
                ->after('import_id')
                ->constrained('liquidation_items')
                ->nullOnDelete();
            
            $table->index('liquidation_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operator_reports', function (Blueprint $table) {
            $table->dropForeign(['liquidation_item_id']);
            $table->dropIndex(['liquidation_item_id']);
            $table->dropColumn('liquidation_item_id');
        });
    }
};
