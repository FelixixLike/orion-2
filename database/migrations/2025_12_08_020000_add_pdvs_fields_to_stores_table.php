<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'id_pdv')) {
                $table->string('id_pdv')->nullable()->after('idpos')->unique();
            }
            if (!Schema::hasColumn('stores', 'circuit_code')) {
                $table->string('circuit_code')->nullable()->after('route_code');
            }
            if (Schema::hasColumn('stores', 'category')) {
                $table->string('category')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'id_pdv')) {
                $table->dropUnique(['id_pdv']);
                $table->dropColumn('id_pdv');
            }
            if (Schema::hasColumn('stores', 'circuit_code')) {
                $table->dropColumn('circuit_code');
            }
        });
    }
};
