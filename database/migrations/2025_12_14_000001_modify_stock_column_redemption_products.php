<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make the existing 'stock' column nullable (allows unlimited stock)
        Schema::table('redemption_products', function (Blueprint $table) {
            $table->integer('stock')
                ->nullable()
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('redemption_products', function (Blueprint $table) {
            $table->integer('stock')
                ->default(0)
                ->change();
        });
    }
};
