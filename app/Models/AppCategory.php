<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppCategory extends Model
{
    protected $guarded = [];
    public function apps()
    {
        return $this->hasMany(AppTo::class,'category_id');
    }
     public function getIconAttribute($val)
    {
        return ($val !== null) ? asset( $val) : "";
    }
}
