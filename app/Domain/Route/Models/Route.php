<?php

namespace App\Domain\Route\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Route extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'origin',
        'destination',
        'distance',
        'estimated_time',
        'active',
    ];
    
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'distance' => 'decimal:2',
            'estimated_time' => 'integer',
        ];
    }

    public static function booted(): void
    {
        static::creating(function (self $route): void {
            if (empty($route->type)) {
                $route->type = 'route';
            }

            if (isset($route->code)) {
                $route->code = Str::upper(trim($route->code));
            }
        });

        static::updating(function (self $route): void {
            if (isset($route->code)) {
                $route->code = Str::upper(trim($route->code));
            }
        });
    }
}
