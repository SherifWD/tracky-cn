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

            // Prepare payload
            $payload = [
                'billNo' => $shipment->track_number ?? '',
                'containerNo' => $shipment->container_no,
                'carrierCode' => $shipment->carrier_code,
                'portCode' => $shipment->port_code,
                'isExport' => $shipment->is_export ?? 'E',
                'dataType' => ['CARRIER'],
            ];

            // Generate signValue
            $secret = config('services.ocean_tracking.secret');
            $signValue = base64_encode(
                hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $secret, true)
            );

            // Prepare headers
            $headers = [
                'Content-Type' => 'application/json',
                'appId' => config('services.ocean_tracking.app_id'),
                'signValue' => $signValue,
            ];

            // Log everything for debug
            Log::info('Tracking Request', [
                'shipment_id' => $shipment->id,
                'headers' => $headers,
                'payload' => $payload,
            ]);

            // Make API call
            $response = Http::withHeaders($headers)
                ->post(config('services.ocean_tracking.url'), $payload);

            Log::info('Tracking Response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $trackingResponse = $response->successful()
                ? $response->json()
                : [
                    'code' => $response->status(),
                    'message' => $response->json('message') ?? 'Tracking failed',
                    'error_details' => $response->json(),
                ];
        } catch (\Exception $e) {
            Log::error('Tracking Exception', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            $trackingResponse = [
                'code' => 500,
                'message' => 'Exception occurred during tracking.',
                'error' => $e->getMessage(),
            ];
        }

        $results[] = [
            'shipping' => $shipment,
            'tracking' => $trackingResponse,
        ];
    }

    return response()->json($results);
}


}
