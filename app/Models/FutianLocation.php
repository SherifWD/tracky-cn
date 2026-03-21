<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FutianLocation extends Model
{
    protected $guarded = [];

    public function setImageAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['image'] = $value;

            return;
        }

        $baseUrl = rtrim(url('/'), '/');

        $this->attributes['image'] = Str::of($value)
            ->replaceStart($baseUrl, '')
            ->ltrim('/')
            ->toString();
    }

    public function getImageAttribute($val)
    {
        return ($val !== null && $val !== '') ? asset($val) : '';
    }
}
