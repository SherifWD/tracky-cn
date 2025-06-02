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
            // Validation
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

            // Prepare Payload for Real-Time Vessel API
            $vesselPayload = [
                'billNo' => $shipment->track_number ?? '',
                'containerNo' => $shipment->container_no,
                'carrierCode' => $shipment->carrier_code,
                'portCode' => $shipment->port_code,
                'isExport' => $shipment->is_export ?? 'E',
                'dataType' => ['CARRIER'],
            ];

            // Get Token First
            $tokenResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://prod-api.4portun.com/openapi/auth/token', [
                'appId' => config('services.ocean_tracking.app_id'),
                'secret' => config('services.ocean_tracking.secret')
            ]);

            $accessToken = $tokenResponse->json('data');

            if (!$accessToken) {
                throw new \Exception('Authorization token not received.');
            }

            // Common headers for both API calls
            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ];

            // Real-Time Vessel Tracking
            $vesselResponse = Http::withHeaders($headers)
                ->post('https://prod-api.4portun.com/openapi/gateway/api/ais/vessel-location', $vesselPayload);

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

            // Port Data Tracking (if subscriptionId exists)
            $portTracking = null;
            if (!empty($shipment->subscription_id)) {
                $portResponse = Http::withHeaders($headers)
                    ->post('https://prod-api.4portun.com/openapi/gateway/api/portdata/query', [
                        'subscriptionId' => $shipment->subscription_id,
                    ]);

                Log::info('Port Data Response', [
                    'shipment_id' => $shipment->id,
                    'status' => $portResponse->status(),
                    'body' => $portResponse->body(),
                ]);

                $portTracking = $portResponse->successful()
                    ? $portResponse->json()
                    : [
                        'code' => $portResponse->status(),
                        'message' => $portResponse->json('message') ?? 'Port data tracking failed',
                        'error_details' => $portResponse->json(),
                    ];
            }

        } catch (\Exception $e) {
            Log::error('Tracking Exception', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            $vesselTracking = [
                'code' => 500,
                'message' => 'Exception during vessel tracking',
                'error' => $e->getMessage(),
            ];

            $portTracking = null;
        }

        $results[] = [
            'shipping' => $shipment,
            'vessel_tracking' => $vesselTracking ?? null,
            'port_tracking' => $portTracking ?? null,
        ];
    }

    return response()->json($results);
}


}
