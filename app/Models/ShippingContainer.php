<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingContainer extends Model
{
    protected $guarded = [];

    public function getImageAttribute($val)
    {
        return $val !== null ? asset($val) : '';
    }
}
