<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FutianLocation extends Model
{
     public function getImageAttribute($val)
    {
        return ($val !== null) ? asset('/'.$val) : "";

    }
}
