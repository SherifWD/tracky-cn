<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReservedShipping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
class SubscriptionController extends Controller
{
public function getAllTrackedShippings()
{
    $shippings = ReservedShipping::with([
        'user',
        'harborFrom',
        'harborTo',
    ])->get();

    $results = [];

    foreach ($shippings as $shipment) {
        try {
            // Validate required fields
            if (
                !$shipment->container_no ||
                !$shipment->carrier_code ||
                !$shipment->port_code
            ) {
                $results[] = [
                    'shipping' => $shipment,
                    'tracking' => [
                        'code' => 422,
                        'message' => 'Missing required tracking fields (container_no, carrier_code, or port_code).',
                    ],
                ];
                continue;
            }

            // Prepare payloads
            $payload1 = [
                'mmsi' => $shipment->subscription_id,
                'billNo' => $shipment->track_number ?? '',
                'containerNo' => $shipment->container_no,
                'carrierCode' => $shipment->carrier_code,
                'portCode' => $shipment->port_code,
                'isExport' => $shipment->is_export ?? 'E',
                'dataType' => ['CARRIER'],
            ];

            $portHistoryPayload = [
                'mmsi' => $shipment->reservation_string,
                'berthTimeStart' => $shipment->bearth_start,
                'berthTimeEnd' => $shipment->bearth_end,
            ];

            // Get token
            $tokenResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://prod-api.4portun.com/openapi/auth/token', [
                'appId' => config('services.ocean_tracking.app_id'),
                'secret' => config('services.ocean_tracking.secret'),
            ]);

            $tok = $tokenResponse->json()['data'];

            $headers = [
                'Content-Type' => 'application/json',
                'appId' => config('services.ocean_tracking.app_id'),
                'Authorization' => $tok,
            ];

            // Vessel tracking call
            $vesselResponse = Http::withHeaders($headers)
                ->post("https://prod-api.4portun.com/openapi/gateway/api/ais/vessel-location", $payload1);

            Log::info('Vessel Tracking Response', [
                'shipment_id' => $shipment->id,
                'status' => $vesselResponse->status(),
                'body' => $vesselResponse->body(),
            ]);

            $vesselTracking = $vesselResponse->successful()
                ? $vesselResponse->json()
                : [
                    'code' => $vesselResponse->status(),
                    'message' => $vesselResponse->json('message') ?? 'Vessel tracking failed',
                    'error_details' => $vesselResponse->json(),
                ];

            // Port tracking call
            $portResponse = Http::withHeaders($headers)
                ->post("https://prod-api.4portun.com/openapi/gateway/api/ais/port-of-call", $portHistoryPayload);

            Log::info('Port Tracking Response', [
                'shipment_id' => $shipment->id,
                'status' => $portResponse->status(),
                'body' => $portResponse->body(),
            ]);

            $portTracking = $portResponse->successful()
                ? $portResponse->json()
                : [
                    'code' => $portResponse->status(),
                    'message' => $portResponse->json('message') ?? 'Port tracking failed',
                    'error_details' => $portResponse->json(),
                ];

            // Final result
            $results[] = [
                'shipping' => $shipment,
                'tracking' => [
                    'vessel_tracking' => $vesselTracking,
                    'port_tracking' => $portTracking,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Tracking Exception', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            $results[] = [
                'shipping' => $shipment,
                'tracking' => [
                    'code' => 500,
                    'message' => 'Exception occurred during tracking.',
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    return response()->json($results);
}


}
