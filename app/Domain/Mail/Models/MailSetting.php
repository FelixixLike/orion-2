<?php

namespace App\Domain\Mail\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $table = 'mail_settings';
    
    protected $fillable = [
        'mailer',
        'host',
        'port',
        'password',
        'encryption',
        'from_address',
        'from_name',
    ];
    
    /**
     * Get the attributes that should be hidden for serialization.
     *
     * @return array<int, string>
     */
    protected function hidden(): array
    {
        return [
            'password',
        ];
    }
    
    /**
     * Obtiene la única instancia de configuración de correo.
     * Crea una nueva si no existe.
     */
    public static function getInstance(): self
    {
        $setting = self::first();
        
        if (!$setting) {
            $setting = new self([
                'mailer' => 'smtp',
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port', 587),
                'encryption' => config('mail.mailers.smtp.encryption', 'tls'),
                'from_address' => config('mail.from.address', 'hello@example.com'),
                'from_name' => config('mail.from.name', 'Orion'),
            ]);
            $setting->save();
        }
        
        return $setting;
    }
}

