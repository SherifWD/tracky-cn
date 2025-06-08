<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContainerPriceByHarbor extends Model
{
    protected $guarded =[];
    protected $casts = [
    'date' => 'date',
];
  public function container()
{
    return $this->belongsTo(ShippingContainer::class, 'container_id');
}
  public function harbor()
{
    return $this->belongsTo(HarborLocation::class, 'harbor_id');
}
}
