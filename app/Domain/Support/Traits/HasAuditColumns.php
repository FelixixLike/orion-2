<?php

namespace App\Domain\Support\Traits;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait HasAuditColumns
{
    public static function bootHasAuditColumns(): void
    {
        static::creating(function ($model) {
            $userId = auth('admin')->id() ?? auth('web')->id();

            if (!$model->created_by && $userId) {
                $model->created_by = $userId;
            }
            if (!$model->updated_by && $userId) {
                $model->updated_by = $userId;
            }
        });

        static::updating(function ($model) {
            $userId = auth('admin')->id() ?? auth('web')->id();

            if ($userId) {
                $model->updated_by = $userId;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
