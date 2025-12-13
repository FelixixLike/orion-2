<?php

namespace App\Domain\User\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $email
 * @property string $status
 * @property string|null $activation_token_plain
 * @property string|null $activation_token
 * @property \Illuminate\Support\Carbon|null $activation_token_expires_at
 * @method bool hasRole($roles, string $guard = null)
 * @method bool hasAnyRole($roles, string $guard = null)
 * @method bool hasAllRoles($roles, string $guard = null)
 * @method void assignRole(...$roles)
 * @method void removeRole($roles)
 * @method void syncRoles($roles)
 * @method \Illuminate\Database\Eloquent\Collection roles()
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, Authorizable;
    use \App\Domain\Support\Traits\HasAuditColumns;

    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'created_by',
        'updated_by',
        'username',
        'first_name',
        'last_name',
        'phone',
        'email',
        'id_type',
        'id_number',
        'password_hash',
        'status',
        'failed_attempts',
        'activation_token',
        'activation_token_expires_at',
        'activation_token_plain',
        'supervisor_route_codes',
        'must_change_password',
        'password_changed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'failed_attempts' => 'integer',
            'activation_token_expires_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'supervisor_route_codes' => 'array',
        ];
    }

    /**
     * Get the attributes that should be hidden for serialization.
     *
     * @return array<int, string>
     */
    protected function hidden(): array
    {
        return [
            'password_hash',
            'remember_token',
            'activation_token_plain', // Ocultar token en texto plano de respuestas JSON
        ];
    }

    /**
     * Get the password field name.
     */
    public function getAuthPasswordName()
    {
        return 'password_hash';
    }

    /**
     * Get the password for authentication.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Obtener el nombre completo del usuario para Filament
     */
    public function getFilamentName(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: $this->email;
    }

    /**
     * Atributo name para Filament (alias de nombre completo)
     */
    public function getNameAttribute(): string
    {
        return $this->getFilamentName();
    }

    /**
     * Get the guard name(s) for the user (for Spatie Permission).
     * Retorna array para soportar mカltiples guards segカn documentaciИn de Spatie.
     */
    public function guardName(): array
    {
        return ['admin', 'retailer'];
    }

    /**
     * RelaciИn muchos-a-muchos con tiendas (retailers multi-tiendas).
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domain\Store\Models\Store::class, 'store_user')->withTimestamps();
    }

    /**
     * Rutas/circuitos asignados a un supervisor (guard admin).
     *
     * @return array<int,string>
     */
    public function supervisorRouteList(): array
    {
        $routes = $this->supervisor_route_codes ?? [];

        if (is_string($routes)) {
            $decoded = json_decode($routes, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $routes = $decoded;
            } else {
                $routes = array_map('trim', explode(',', $routes));
            }
        }

        return collect($routes)
            ->filter()
            ->map(fn($route) => trim((string) $route))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Tiendas donde es tendero principal (stores.user_id).
     */
    public function ownedStores(): HasMany
    {
        return $this->hasMany(\App\Domain\Store\Models\Store::class, 'user_id');
    }

    public function handledRedemptions(): HasMany
    {
        return $this->hasMany(\App\Domain\Store\Models\Redemption::class, 'handled_by_user_id');
    }
}
