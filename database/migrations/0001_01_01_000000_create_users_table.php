<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Habilitar extensión citext
        DB::statement('CREATE EXTENSION IF NOT EXISTS citext');

        // Limpiar enums previos (migrate:fresh no elimina tipos)
        DB::statement('DROP TYPE IF EXISTS user_status_enum CASCADE');
        DB::statement('DROP TYPE IF EXISTS id_type_enum CASCADE');

        // Crear tipos ENUM
        DB::statement("CREATE TYPE user_status_enum AS ENUM ('active', 'inactive', 'suspended')");
        DB::statement("CREATE TYPE id_type_enum AS ENUM ('CC','CE','NIT','TI','PAS')");

        // Crear tabla users
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique()->nullable();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('phone', 20)->nullable();
            $table->string('id_number', 30);
            $table->text('password_hash')->nullable(); // Nullable para usuarios pendientes de activación
            $table->integer('failed_attempts')->default(0);
            $table->rememberToken();
            $table->timestampsTz();
        });

        // Agregar columnas con tipos personalizados
        DB::statement("ALTER TABLE users ADD COLUMN email CITEXT UNIQUE");
        DB::statement("ALTER TABLE users ADD COLUMN id_type id_type_enum NOT NULL");
        DB::statement("ALTER TABLE users ADD COLUMN status user_status_enum NOT NULL DEFAULT 'inactive'");
        DB::statement("ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMPTZ");
        DB::statement("ALTER TABLE users ADD COLUMN phone_verified_at TIMESTAMPTZ");
        DB::statement("ALTER TABLE users ADD COLUMN last_login_at TIMESTAMPTZ");
        DB::statement("ALTER TABLE users ADD COLUMN last_login_ip INET");
        DB::statement("ALTER TABLE users ADD COLUMN locked_until TIMESTAMPTZ");

        // Constraint único compuesto para id_type + id_number
        DB::statement("ALTER TABLE users ADD CONSTRAINT uq_users_id_type_number UNIQUE (id_type, id_number)");

        // Validación de teléfono E.164
        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT chk_users_phone_e164
            CHECK (
                phone IS NULL OR phone ~ '^\+[1-9][0-9]{6,14}$'
            )
        ");

        // Validación de email
        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT chk_users_email_format
            CHECK (
                email IS NULL OR email ~ '^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$'
            )
        ");

        // Tabla password_reset_tokens
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        
        DB::statement("DROP TYPE IF EXISTS user_status_enum");
        DB::statement("DROP TYPE IF EXISTS id_type_enum");
    }
};
