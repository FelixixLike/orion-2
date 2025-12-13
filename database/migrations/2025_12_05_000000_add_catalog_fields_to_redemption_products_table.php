<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redemption_products', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('sku');
            $table->text('description')->nullable()->after('image_url');
            $table->unsignedInteger('stock')->default(0)->after('unit_value');
        });
    }

    public function down(): void
    {
        Schema::table('redemption_products', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'description', 'stock']);
        });
    }
};
