<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'singleton' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProjectSetting $setting): void {
            $setting->singleton = true;
        });
    }
}
