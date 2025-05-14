<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppTo extends Model
{
    protected $guarded =[];
    public function getIconAttribute($val)
    {
        return ($val !== null) ? asset( $val) : "";
    }
}
