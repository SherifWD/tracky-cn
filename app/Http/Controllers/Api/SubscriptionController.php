<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReservedShipping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    


public function getAllTrackedShippings()
{
    $shippings = ReservedShipping::with([
        'container',        // Make sure container has container_no
        'user',
        'harborFrom',
        'harborTo',
    ])->get();

    $results = [];

    foreach ($shippings as $shipment) {
        $trackingResponse = null;

        // Check required fields before making API call
        if (
            $shipment->container &&
            $shipment->container->container_no &&
            $shipment->carrier_code &&
            $shipment->port_code
        ) {
            $payload = [
                'billNo' => $shipment->track_number ?? '',
                'containerNo' => $shipment->container->container_no,
                'carrierCode' => $shipment->carrier_code,
                'portCode' => $shipment->port_code,
                'isExport' => $shipment->is_export ?? 'E',
                'dataType' => ['CARRIER'],
            ];

            $secret = config('services.ocean_tracking.secret');
            $signValue = base64_encode(
                hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $secret, true)
            );

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'appId' => config('services.ocean_tracking.app_id'),
                'signValue' => $signValue,
            ])->post(config('services.ocean_tracking.url'), $payload);

            if ($response->successful()) {
                $trackingResponse = $response->json();
            } else {
                $trackingResponse = [
                    'code' => $response->status(),
                    'message' => $response->json('message') ?? 'Tracking failed'
                ];
            }
        } else {
            $trackingResponse = [
                'code' => 422,
                'message' => 'Missing required fields for tracking',
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
