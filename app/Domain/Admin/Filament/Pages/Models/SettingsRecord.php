<?php

/*
 * Copyright (c) 2025 Andrés Felipe Martínez González, Nelson Steven Reina Moreno, Gissel Tatiana Parrado Moreno.
 * All rights reserved. See LICENSE.md for usage terms.
 */
namespace App\Domain\Admin\Filament\Pages\Models;

use Illuminate\Database\Eloquent\Model;

class SettingsRecord extends Model
{
    protected $table = 'settings_records';
    
    public $incrementing = false;
    
    protected $keyType = 'string';
    
    protected $fillable = [
        'id',
        'category',
        'label',
        'description',
        'url',
    ];
    
    public $timestamps = false;
}

