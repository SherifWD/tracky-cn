<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AppCategory;
use App\Models\Commission;
use App\Models\Country;
use App\Models\HarborLocation;
use App\Models\HomeImage;
use App\Models\ReservedShipping;
use App\Models\ShippingContainer;
use App\Models\ShippingLineIcon;
use App\Models\SourcesContact;
use App\Models\TicketsContact;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MetaController extends Controller
{
    use HelpersTrait;
    use backendTraits;
   public function getMetaData()
{
    $user = auth()->user();

    // Update user's shipments that are not yet finalized
    $shipments = ReservedShipping::where('user_id', $user->id)
        ->where('status', '!=', 3)
        ->get();

    foreach ($shipments as $shipment) {
        try {
            // Skip if required fields are missing
            if (!$shipment->track_number || !$shipment->carrier_code) {
                continue;
            }

            // Get auth token
            $tokenResponse = Http::post('https://api.trackingeyes.com/api/auth/authorization', [
                'companyCode' => 100220,
                'secret' => '2d038e6d-07ae-4354-aebf-f924c198e9c2'
            ]);

            $authToken = $tokenResponse->json()['result'] ?? null;
            if (!$authToken) {
                continue;
            }

            // Get shipment details
            $detailsResponse = Http::get('https://api.trackingeyes.com/api/oceanbill/batchOceanBill', [
                'companyCode' => 100220,
                'token' => $authToken
            ], [[
                'referenceNo' => $shipment->track_number,
                'blType' => 'BL',
                'carrierCd' => $shipment->carrier_code
            ]]);

            $details = $detailsResponse->json();
            
            // Determine status based on trackStatusCd
            $statusCode = $shipment->status;
            if ($shipment->status !== 3) {
                $trackStatus = $details['result'][0]['trackStatusCd'] ?? null;
                
                switch ($trackStatus) {
                    case 'T': // Tracking
                        $statusCode = 1;
                        break;
                    case 'E': // Ended
                        $statusCode = 2;
                        break;
                    default:
                        $statusCode = 0;
                }
            }

            // Save only if changed
            if ($shipment->status !== 3 && $shipment->status != $statusCode) {
                $shipment->status = $statusCode;
                $shipment->save();
            }

        } catch (\Exception $e) {
            \Log::error("Error updating shipment ID {$shipment->id}: " . $e->getMessage());
            continue;
        }
    }

    // Return all metadata as normal
    $data['commission'] = Commission::all();
    $data['country'] = Country::all();
    $data['apps'] = AppCategory::with('apps')->get();
    $data['shipping_icons'] = ShippingLineIcon::all();
    $data['ticket_contact'] = TicketsContact::all();
    $data['sources_contact'] = SourcesContact::all();
    $data['shipping_container'] = ShippingContainer::all();
    $data['harbor_locations'] = HarborLocation::all();

    return $this->returnData('meta_data', $data, "Meta Data Returned");
}


}
