<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarborLocation extends Model
{
    protected $guarded =[];
     public function country()
{
    return $this->belongsTo(Country::class);
}
}
