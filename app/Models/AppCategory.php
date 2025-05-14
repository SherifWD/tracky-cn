<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppCategory extends Model
{
    public function apps()
    {
        return $this->belongsTo(AppTo::class);
    }
}
