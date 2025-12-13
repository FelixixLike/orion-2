<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->string('mailer')->default('smtp')->comment('smtp, postmark, resend, ses, log');
            $table->string('host')->nullable();
            $table->integer('port')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('encryption')->nullable()->comment('tls, ssl');
            $table->string('from_address');
            $table->string('from_name');
            
            // Para servicios de terceros
            $table->text('postmark_token')->nullable();
            $table->text('resend_key')->nullable();
            $table->text('ses_key')->nullable();
            $table->text('ses_secret')->nullable();
            $table->string('ses_region')->nullable()->default('us-east-1');
            
            $table->timestamps();
        });
        
        // Insertar registro único por defecto solo si no existe
        if (DB::table('mail_settings')->count() === 0) {
            DB::table('mail_settings')->insert([
                'mailer' => config('mail.default', 'log'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port', 2525),
                'username' => config('mail.mailers.smtp.username'),
                'encryption' => config('mail.mailers.smtp.encryption'),
                'from_address' => config('mail.from.address', 'hello@example.com'),
                'from_name' => config('mail.from.name', 'Orion'),
                'postmark_token' => config('services.postmark.token'),
                'resend_key' => config('services.resend.key'),
                'ses_key' => config('services.ses.key'),
                'ses_secret' => config('services.ses.secret'),
                'ses_region' => config('services.ses.region', 'us-east-1'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
