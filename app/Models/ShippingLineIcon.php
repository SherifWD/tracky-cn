<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingLineIcon extends Model
{
    protected $guarded =[]; 
    public function getIconAttribute($val)
    {
        return ($val !== null) ? asset( $val) : "";
    }
}
