<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'supervisor_route_codes')) {
                $table->json('supervisor_route_codes')->nullable()->after('activation_token_plain')->comment('Listado de rutas/circuitos asignados al supervisor (guard admin)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'supervisor_route_codes')) {
                $table->dropColumn('supervisor_route_codes');
            }
        });
    }
};
