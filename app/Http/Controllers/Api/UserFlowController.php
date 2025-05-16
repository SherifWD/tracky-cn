<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContainerPriceByHarbor;
use App\Models\Country;
use App\Models\ReceiptPayment;
use App\Models\ReservedShipping;
use App\Models\ReserveTranslator;
use App\Models\ShippingContainer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserFlowController extends Controller
{
    use HelpersTrait;
    use backendTraits;

    public function calculateReservationPrice(Request $request)
{
    $validator = Validator::make($request->all(), [
        'country_id' => 'required|exists:countries,id',
        'from_date' => 'required|date',
        'to_date' => 'required|date|after_or_equal:from_date',
    ]);

    if ($validator->fails()) {
        return $this->returnValidationError('E001', $validator);
    }

    // Parse dates
    $from = Carbon::parse($request->from_date);
    $to = Carbon::parse($request->to_date);

    // Calculate number of days
    $days = $from->diffInDays($to) + 1; // +1 to include the start day
    $data = $request->all();
$data['user_id'] = auth()->id();
// ReserveTranslator::create($data);

    // Get translator rate
    $country = Country::find($request->country_id);
    $translatorRate = $country->translator_rate;
    // Calculate price
    $price = $translatorRate * $days;
    $data['days'] = $days;
    $data['daily_rate'] = $translatorRate;
    $data['total_price'] = $price;
    return $this->returnData('data',$data,'Price of the Translator Reservation');
}
    public function reserveTranslator(Request $request){

        $validator = Validator::make($request->all(), [
        'country_id' => 'required|exists:countries,id',
        'from_date' => 'required',
        'to_date' => 'required',
        'price' => 'required',
    ]);

    if ($validator->fails()) {
        return $this->returnValidationError('E001', $validator);
    }
    // $request->user_id = Auth::user()->id;
$data = $request->all();
$data['user_id'] = auth()->id();
ReserveTranslator::create($data);
        // $reservation = ReserveTranslator::Create($request->all());
        
        if($data){
            return $this->returnData('translator',$data,'Reserved Translator Successfully');
        }else
        return $this->returnError('404','Failed to reserve translator');

    }


public function calculateCBM(Request $request)
{
    $validator = Validator::make($request->all(), [
        'length' => 'required|numeric',
        'width' => 'required|numeric',
        'height' => 'required|numeric',
        'unit' => 'required|in:cm,m,in',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }

    $length = $request->input('length');
    $width = $request->input('width');
    $height = $request->input('height');
    $unit = $request->input('unit');

    // Convert dimensions to meters
    switch ($unit) {
        case 'cm':
            $length /= 100;
            $width /= 100;
            $height /= 100;
            break;
        case 'in':
            $length *= 0.0254;
            $width *= 0.0254;
            $height *= 0.0254;
            break;
        // 'm' requires no conversion
    }

    $cbm_m3 = round($length * $width * $height, 6);

    // Convert to ft³ (1 m³ = 35.3147 ft³)
    $cbm_ft3 = round($cbm_m3 * 35.3147, 6);

    return response()->json([
        'success' => true,
        'cbm_m3' => $cbm_m3,
        'cbm_ft3' => $cbm_ft3,
        'unit_m3' => 'm3',
        'unit_ft3' => 'ft3',
    ]);
}
public function ReceiptPayment(Request $request)
{
    $validator = Validator::make($request->all(), [
        'from_country_id' => 'required|exists:countries,id',
        'to_country_id' => 'required|exists:countries,id',
        'original_price' => 'required|numeric|min:0',
        'commission_rate' => 'nullable|numeric', 
        'usd_conversion' => 'nullable|numeric|min:0', // Optional override
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }

    $originalPrice = $request->original_price;
    $commissionRate = $request->commission_rate; // default 10%
    $usdConversion = $request->usd_conversion;   // example: 1 USD = 30 local currency

    $receipt = ReceiptPayment::create([
        'from_country_id' => $request->from_country_id,
        'to_country_id' => $request->to_country_id,
        'original_price' => $originalPrice,
        'after_commission_price' => $commissionRate,
        'usd_conversion' => $usdConversion,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Receipt payment recorded successfully.',
        'data' => $receipt
    ]);
}
public function getPriceContainerByHarbor(Request $request){
    $validator = Validator::make($request->all(), [
        'container_id' => 'required|numeric|exists:shipping_containers,id',
        'harbor_id' => 'required|numeric|exists:harbor_locations,id',
        
    ]);
    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }
    $price['price'] = ContainerPriceByHarbor::where('container_id',$request->container_id)->where('harbor_id',$request->harbor_id)->orderByDesc('id')->first()->base_price;
    return $this->returnData('price',$price,'Price of the Container');
}
public function reserveShipping(Request $request){
    $validator = Validator::make($request->all(), [
        'container_id' => 'required|numeric|exists:shipping_containers,id',
        'harbor_id_from' => 'required|numeric|exists:harbor_locations,id',
        'harbor_id_to' => 'required|numeric|exists:harbor_locations,id',
        'date' => 'required|date',
        'price' => 'required|numeric',
    ]);
    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }
    if(ContainerPriceByHarbor::where('container_id',$request->container_id)->where('harbor_id',$request->harbor_id_from)->where('base_price',$request->price)->first())
    $price_id = ContainerPriceByHarbor::where('container_id',$request->container_id)->where('harbor_id',$request->harbor_id_from)->where('base_price',$request->price)->first()->id;
    else
    return $this->returnError('404','Not Found');

    $reserve = new ReservedShipping();
    $reserve->user_id = auth()->id();
    $reserve->container_id = $request->container_id;
    $reserve->harbor_id_from = $request->harbor_id_from;
    $reserve->harbor_id_to = $request->harbor_id_to;
    $reserve->date = $request->date;
    $reserve->base_price = $request->price;
    $reserve->container_price_id = $price_id;
    $reserve->save();
    return $this->returnData('data',$reserve,'Shipping Reserved, Please Wait for Confirmation');

}

}
