<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->nullable()->index();
            $table->string('color')->nullable(); // Para mantener los colores de los badges
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_categories');
    }
};
