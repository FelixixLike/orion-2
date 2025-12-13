<?php

declare(strict_types=1);

namespace App\Domain\Store\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use App\Domain\Support\Traits\HasAuditColumns;

class RedemptionProduct extends Model
{
    use HasFactory;
    use HasAuditColumns;

    protected $fillable = [
        'name',
        'type',
        'sku',
        'image_url',
        'image_path',
        'description',
        'stock',
        'unit_value',
        'is_active',
        'monthly_store_limit',
        'max_value',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_value' => 'decimal:2',
            'is_active' => 'boolean',
            'stock' => 'integer',
            'monthly_store_limit' => 'integer',
            'max_value' => 'decimal:2',
        ];
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        if (!empty($this->attributes['image_url'])) {
            $url = $this->attributes['image_url'];
            return str_starts_with($url, 'http') ? $url : asset($url);
        }

        return asset('images/store/item.png');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    public function hasHistory(): bool
    {
        return $this->redemptions()->exists();
    }
}
