<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\Commission;
use App\Models\Country;
use App\Models\FlightApp;
use App\Models\ShippingLineIcon;
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
        $data['flight_apps'] = FlightApp::all();
        $data['shipping_icons'] = ShippingLineIcon::all();

        return $this->returnData('meta_data',$data,"Meta Data Returned");
    }
}
