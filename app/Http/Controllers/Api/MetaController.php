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
            if (
                !$shipment->container_no ||
                !$shipment->carrier_code ||
                !$shipment->port_code
            ) {
                continue;
            }

            // Get tracking token
            $tokenResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://prod-api.4portun.com/openapi/auth/token', [
                'appId' => config('services.ocean_tracking.app_id'),
                'secret' => config('services.ocean_tracking.secret'),
            ]);

            $authToken = $tokenResponse->json()['data'] ?? null;

            if (!$authToken) {
                \Log::warning("No tracking token received for shipment ID: {$shipment->id}");
                continue;
            }

            $headers = [
                'Content-Type' => 'application/json',
                'appId' => config('services.ocean_tracking.app_id'),
                'Authorization' => $authToken,
            ];

            // Port tracking request
            $portPayload = [
                'mmsi' => $shipment->subscription_id,
                'berthTimeStart' => $shipment->berth_start,
                'berthTimeEnd' => $shipment->berth_end,
            ];

            $portResponse = Http::withHeaders($headers)
                ->post("https://prod-api.4portun.com/openapi/gateway/api/ais/port-of-call", $portPayload);

            $portTracking = $portResponse->successful()
                ? $portResponse->json()
                : [];

            // Vessel tracking request
            $vesselPayload = [
                'mmsi' => $shipment->subscription_id,
                'billNo' => $shipment->track_number ?? '',
                'containerNo' => $shipment->container_no,
                'carrierCode' => $shipment->carrier_code,
                'portCode' => $shipment->port_code,
                'isExport' => $shipment->is_export ?? 'E',
                'dataType' => ['CARRIER'],
            ];

            $vesselResponse = Http::withHeaders($headers)
                ->post("https://prod-api.4portun.com/openapi/gateway/api/ais/vessel-location", $vesselPayload);

            $vesselTracking = $vesselResponse->successful()
                ? $vesselResponse->json()
                : [];

            // Determine status
            $statusCode = $shipment->status;

            if ($shipment->status === 3) {
                $statusCode = 3;
            } elseif (
                isset($portTracking['code']) && $portTracking['code'] === 200
            ) {
                $portData = $portTracking['data'] ?? null;

                if (is_array($portData) && empty($portData)) {
                    $statusCode = 1;
                } elseif (is_array($portData) && !empty($portData)) {
                    $statusCode = 2;
                } elseif (is_null($portData)) {
                    $statusCode = 1; // data key is missing, treat as empty
                }
            } elseif (
                isset($vesselTracking['code']) && $vesselTracking['code'] === 200 &&
                isset($vesselTracking['data']) && !empty($vesselTracking['data'])
            ) {
                $statusCode = 1; // fallback when port tracking fails
            } else {
                $statusCode = 0; // default if all tracking failed
            }

            // Save only if changed and not already status 3
            if ($shipment->status !== 3 && $shipment->status != $statusCode) {
                $shipment->status = $statusCode;
                $shipment->save();
                \Log::info("Updated shipment ID {$shipment->id} to status {$statusCode}");
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
