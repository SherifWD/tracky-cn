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
    $shippings = ReservedShipping::with(['user', 'harborFrom', 'harborTo'])->get();
    $results = [];

    // Get the bearer token once
    try {
        $tokenResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://prod-api.4portun.com/openapi/auth/token', [
            'appId' => config('services.ocean_tracking.app_id'),
            'secret' => config('services.ocean_tracking.secret'),
        ]);

        if (!$tokenResponse->successful()) {
            throw new \Exception("Token fetch failed: " . $tokenResponse->body());
        }

        $bearerToken = $tokenResponse->json()['data'] ?? null;
        if (!$bearerToken) {
            throw new \Exception("Token data missing from response.");
        }
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to retrieve bearer token.',
            'details' => $e->getMessage(),
        ], 500);
    }

    foreach ($shippings as $shipment) {
        $vesselTracking = null;
        $portDataTracking = null;

        try {
            // ========= 1. Vessel Real-Time Location =========
            if ($shipment->ship_name) {
                $payload = [
                    'searchKey' => $shipment->ship_name,
                    'searchType' => 'ENAME', // or 'IMO' if using IMO number
                ];
dd($bearerToken);
                $headers = [
                    'Content-Type' => 'application/json',
                    'appId' => config('services.ocean_tracking.app_id'),
                    'Authorization' => 'Bearer '.$bearerToken,
                ];

                Log::info('Vessel Location Request', ['shipment_id' => $shipment->id, 'payload' => $payload]);

                $response = Http::withHeaders($headers)
                    ->post('https://prod-api.4portun.com/openapi/gateway/api/ais/singleShip', $payload);

                Log::info('Vessel Location Response', [
                    'shipment_id' => $shipment->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                if ($response->successful() && isset($response['data'])) {
                    $data = $response['data'];
                    $shipment->update([
                        'ship_lat'     => $data['lat'] ?? null,
                        'ship_lon'     => $data['lon'] ?? null,
                        'ship_status'  => $data['naviStat'] ?? null,
                        'ship_eta'     => $data['eta'] ?? null,
                        'ship_speed'   => $data['sog'] ?? null,
                    ]);
                    $vesselTracking = $data;
                } else {
                    $vesselTracking = [
                        'code' => $response->status(),
                        'message' => $response->json('message') ?? 'Vessel tracking failed',
                        'error_details' => $response->json(),
                    ];
                }
            }

            // ========= 2. Port Data by Subscription ID =========
            if ($shipment->subscription_id) {
                $portPayload = ['subscriptionId' => $shipment->subscription_id];
                $signSecret = config('services.ocean_tracking.secret');
                $signValue = base64_encode(
                    hash_hmac('sha256', json_encode($portPayload, JSON_UNESCAPED_UNICODE), $signSecret, true)
                );

                $portHeaders = [
                    'Content-Type' => 'application/json',
                    'appId' => config('services.ocean_tracking.app_id'),
                    'signValue' => $signValue,
                ];

                Log::info('Port Data Request', ['shipment_id' => $shipment->id, 'payload' => $portPayload]);

                $portResponse = Http::withHeaders($portHeaders)
                    ->post('https://prod-api.4portun.com/openapi/gateway/api/portdata/query', $portPayload);

                Log::info('Port Data Response', [
                    'shipment_id' => $shipment->id,
                    'status' => $portResponse->status(),
                    'body' => $portResponse->body(),
                ]);

                if ($portResponse->successful() && isset($portResponse['data'])) {
                    $d = $portResponse['data'];
                    $shipment->update([
                        'vessel_name'   => $d['vesselName'] ?? null,
                        'voyage'        => $d['voyage'] ?? null,
                        'imo_number'    => $d['imoNo'] ?? null,
                        'call_sign'     => $d['callSign'] ?? null,
                        'terminal_code' => $d['terminalCode'] ?? null,
                        'terminal_name' => $d['terminalName'] ?? null,
                        'eta'           => $d['eta'] ?? null,
                        'etd'           => $d['etd'] ?? null,
                        'ata'           => $d['ata'] ?? null,
                        'atd'           => $d['atd'] ?? null,
                    ]);
                    $portDataTracking = $d;
                } else {
                    $portDataTracking = [
                        'code' => $portResponse->status(),
                        'message' => $portResponse->json('message') ?? 'Port data fetch failed',
                        'error_details' => $portResponse->json(),
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Tracking Exception', ['shipment_id' => $shipment->id, 'error' => $e->getMessage()]);
        }

        $results[] = [
            'shipping' => $shipment->fresh(), // ensure updated values are included
            'vessel_tracking' => $vesselTracking,
            'port_tracking' => $portDataTracking,
        ];
    }

    return response()->json($results);
}


}
