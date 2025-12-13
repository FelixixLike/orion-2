<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redemption_products', function (Blueprint $table) {
            if (!Schema::hasColumn('redemption_products', 'image_path')) {
                $table->string('image_path')->nullable()->after('image_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('redemption_products', function (Blueprint $table) {
            if (Schema::hasColumn('redemption_products', 'image_path')) {
                $table->dropColumn('image_path');
            }
        });
    }
};
