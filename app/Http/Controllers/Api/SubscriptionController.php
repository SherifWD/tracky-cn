<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReservedShipping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
public function getAllTrackedShippings(Request $status)
{
    $status = $status->query('status');
    $shippings = ReservedShipping::with([
        'user',
        'harborFrom',
        'harborTo',
        'container'
    ])->where('user_id',auth()->id());
    if (!is_null($status)) {
    $shippings->where('status', '!=', $status);
    }

    $shippings = $shippings->get();
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
                'mmsi' => $shipment->subscription_id,
                'berthTimeStart' => $shipment->berth_start,
                'berthTimeEnd' => $shipment->berth_end,
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

$statusCode = null;


if ($shipment->status === 0) {
    $statusCode = 0;
} elseif ($shipment->status === 3) {
    $statusCode = 3;
} elseif (
    isset($portTracking['code']) && $portTracking['code'] === 200 &&
    isset($portTracking['data']) && is_array($portTracking['data']) && empty($portTracking['data'])
) {
    $statusCode = 1;
} elseif (
    isset($portTracking['code']) && $portTracking['code'] === 200 &&
    isset($portTracking['data']) && is_array($portTracking['data']) && !empty($portTracking['data'])
) {
    $statusCode = 2;
}

$results[] = [
    'shipping' => $shipment,
    'tracking' => [
        'vessel_tracking' => $vesselTracking,
        'port_tracking' => $portTracking,
    ],
];
        }
    }

    return response()->json($results);
}

public function searchShippment(Request $request)
{
    $validator = Validator::make($request->all(), [
        'bill_no' => 'required|string',
        'port_code' => 'required|string',
        'is_export' => 'required|string', // 'E' or 'I'
        // Optionally: 'yard_code' and 'carrier_code' if needed for some ports
    ]);

    if ($validator->fails()) {
        return response()->json([
            'code' => 422,
            'message' => 'Validation error: bill_no, port_code, and is_export are required',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        // 1. Get Auth Token
        $tokenResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://prod-api.4portun.com/openapi/auth/token', [
            'appId' => config('services.ocean_tracking.app_id'),
            'secret' => config('services.ocean_tracking.secret'),
        ]);
        $authToken = $tokenResponse->json()['data'];

        $headers = [
            'Content-Type' => 'application/json',
            'appId' => config('services.ocean_tracking.app_id'),
            'Authorization' => $authToken,
        ];

        // 2. Call China EIR-Subscription API with Bill Number
        $eirPayload = [
            'billNo' => $request->input('bill_no'),
            'portCode' => $request->input('port_code'),
            'isExport' => $request->input('is_export'),
            'yardCode' => $request->input('yard_code', ''),
            'carrierCode' => $request->input('carrier_code', ''),
        ];

        $eirResponse = Http::withHeaders($headers)
            ->post('https://prod-api.4portun.com/api/cn-eir/subscribe', $eirPayload);

        $eirJson = $eirResponse->json();

        if (($eirJson['code'] ?? 500) != 200 || empty($eirJson['data']['subscriptionId'])) {
            return response()->json([
                'code' => $eirJson['code'] ?? 500,
                'message' => $eirJson['message'] ?? 'Could not subscribe with this bill number',
                'data' => $eirJson['data'] ?? null,
            ], 404);
        }

        $subscriptionId = $eirJson['data']['subscriptionId'];

        // 3. Query Tracking Details via getOceanTracking API
        $trackPayload = ['subscriptionId' => $subscriptionId];
        $trackResponse = Http::withHeaders($headers)
            ->post('https://prod-api.4portun.com/api/v2/getOceanTracking', $trackPayload);

        $trackJson = $trackResponse->json();

        // Optionally fetch vessel info or location if needed
        // Example (not always needed):
        // $vesselName = $trackJson['data']['firstVessel']['vessel'] ?? null;

        return response()->json([
            'tracking' => $trackJson,
            'eir' => $eirJson,
        ]);

    } catch (\Exception $e) {
        \Log::error('SearchShipment Exception: ' . $e->getMessage());
        return response()->json([
            'code' => 500,
            'message' => 'Internal error while processing shipment tracking.',
        ], 500);
    }
}




public function getTrackedShippingByID(Request $status,$id)
{
    $status = $status->query('status');
    $shippings = ReservedShipping::with([
        'user',
        'harborFrom',
        'harborTo',
        'container'
    ])->where('user_id',auth()->id());
    if (!is_null($status)) {
    $shippings->where('status', '!=', $status);
    }

    $shipment = $shippings->find($id);
    $results = [];

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
                'mmsi' => $shipment->subscription_id,
                'berthTimeStart' => $shipment->berth_start,
                'berthTimeEnd' => $shipment->berth_end,
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

$statusCode = null;


if ($shipment->status === 0) {
    $statusCode = 0;
} elseif ($shipment->status === 3) {
    $statusCode = 3;
} elseif (
    isset($portTracking['code']) && $portTracking['code'] === 200 &&
    isset($portTracking['data']) && is_array($portTracking['data']) && empty($portTracking['data'])
) {
    $statusCode = 1;
} elseif (
    isset($portTracking['code']) && $portTracking['code'] === 200 &&
    isset($portTracking['data']) && is_array($portTracking['data']) && !empty($portTracking['data'])
) {
    $statusCode = 2;
}

$results[] = [
    'shipping' => $shipment,
    'tracking' => [
        'vessel_tracking' => $vesselTracking,
        'port_tracking' => $portTracking,
    ],
];
        }
    

    return response()->json($results);
}

}
