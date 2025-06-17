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
        'search_num' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'code' => 422,
            'message' => 'Validation error: search_num is required',
            'errors' => $validator->errors()
        ], 422);
    }

    $search = $request->input('search_num');

    $shipment = ReservedShipping::with(['user', 'harborFrom', 'harborTo', 'container'])
        ->where('user_id', auth()->id())
        ->where(function ($query) use ($search) {
            $query->where('track_number', 'LIKE', "%$search%")
                  ->orWhere('container_no', 'LIKE', "%$search%");
        })
        ->first();

    if (!$shipment) {
        return response()->json([
            'code' => 404,
            'message' => 'Shipment not found.',
        ], 404);
    }

    // Validate required fields
    if (
        !$shipment->container_no ||
        !$shipment->carrier_code ||
        !$shipment->port_code
    ) {
        return response()->json([
            'shipping' => $shipment,
            'tracking' => [
                'code' => 422,
                'message' => 'Missing required tracking fields (container_no, carrier_code, or port_code).',
            ],
        ]);
    }

    try {
        // Get token
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

        $portPayload = [
            'mmsi' => $shipment->subscription_id,
            'berthTimeStart' => $shipment->berth_start,
            'berthTimeEnd' => $shipment->berth_end,
        ];

        // Vessel tracking
        $vesselResponse = Http::withHeaders($headers)
            ->post("https://prod-api.4portun.com/openapi/gateway/api/ais/vessel-location", $payload1);

        $vesselTracking = $vesselResponse->successful()
            ? $vesselResponse->json()
            : [
                'code' => $vesselResponse->status(),
                'message' => $vesselResponse->json('message') ?? 'Vessel tracking failed',
                'error_details' => $vesselResponse->json(),
            ];

        // Port tracking
        $portResponse = Http::withHeaders($headers)
            ->post("https://prod-api.4portun.com/openapi/gateway/api/ais/port-of-call", $portPayload);

        $portTracking = $portResponse->successful()
            ? $portResponse->json()
            : [
                'code' => $portResponse->status(),
                'message' => $portResponse->json('message') ?? 'Port tracking failed',
                'error_details' => $portResponse->json(),
            ];

        // Determine custom status
        $statusCode = $shipment->status;
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

        return response()->json([
            'shipping' => $shipment,
            'tracking' => [
                'vessel_tracking' => $vesselTracking,
                'port_tracking' => $portTracking,
            ],
            'status' => $statusCode,
        ]);
    } catch (\Exception $e) {
        \Log::error('SearchShipment Exception: ' . $e->getMessage());
        return response()->json([
            'code' => 500,
            'message' => 'Internal error while processing tracking data.',
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
