<?php

namespace App\Domain\Store\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StoreCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'color'];
}
