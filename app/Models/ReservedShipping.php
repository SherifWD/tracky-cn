<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservedShipping extends Model
{
    protected $guarded =[];
    protected $casts = [
        'saved_via_search' => 'boolean',
    ];
    public function container()
{
    return $this->belongsTo(ShippingContainer::class, 'container_id');
}

public function user()
{
    return $this->belongsTo(User::class);
}

public function harborFrom()
{
    return $this->belongsTo(HarborLocation::class, 'harbor_id_from');
}

public function harborTo()
{
    return $this->belongsTo(HarborLocation::class, 'harbor_id_to');
}

}
