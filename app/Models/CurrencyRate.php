<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    protected $guarded =[];
    protected $casts = [
    'date' => 'date',
];

}
