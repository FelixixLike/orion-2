<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tables to add audit columns to.
     */
    protected array $tables = [
        'stores',
        'simcards',
        'sales_conditions',
        'operator_reports',
        'recharges',
        'liquidations',
        'liquidation_items',
        'redemptions',
        'redemption_products',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    if (!Schema::hasColumn($table->getTable(), 'created_by')) {
                        $table->foreignId('created_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
                    }
                    if (!Schema::hasColumn($table->getTable(), 'updated_by')) {
                        $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    // Drop foreign keys first if they exist
                    // Note: Naming convention might vary, usually table_created_by_foreign
                    $table->dropForeign("{$tableName}_created_by_foreign");
                    $table->dropForeign("{$tableName}_updated_by_foreign");

                    $table->dropColumn(['created_by', 'updated_by']);
                });
            }
        }
    }
};
