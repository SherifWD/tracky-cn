<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FutianLocation extends Model
{
    protected $guarded =[];
     public function getImageAttribute($val)
    {
        return ($val !== null) ? asset('/'.$val) : "";

    }
}
