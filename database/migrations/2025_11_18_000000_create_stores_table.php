<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('idpos', 20)->unique();
            $table->string('name');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('municipality')->nullable();
            $table->string('route_code', 20)->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('address')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
