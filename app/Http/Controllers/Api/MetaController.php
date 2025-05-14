<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AppCategory;
use App\Models\Commission;
use App\Models\Country;
use App\Models\HomeImage;
use App\Models\ShippingLineIcon;
use App\Models\SourcesContact;
use App\Models\TicketsContact;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;
use Illuminate\Http\Request;
class MetaController extends Controller
{
    use HelpersTrait;
    use backendTraits;
    public function getMetaData(){
        $data['commission'] = Commission::all();
        $data['country'] = Country::all();
        $data['apps'] = AppCategory::with('apps')->get();
        $data['shipping_icons'] = ShippingLineIcon::all();
        $data['ticket_contact'] = TicketsContact::all();
        $data['sources_contact'] = SourcesContact::all();
        return $this->returnData('meta_data',$data,"Meta Data Returned");
    }
}
