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
            if (!$shipment->track_number || !$shipment->carrier_code) {
                $results[] = [
                    'shipping' => $shipment,
                    'tracking' => [
                        'code' => 422,
                        'message' => 'Missing required tracking fields (track_number, carrier_code).',
                    ],
                ];
                continue;
            }

            // Get auth token
            $tokenResponse = Http::post('https://api.trackingeyes.com/api/auth/authorization', [
                'companyCode' => 100220,
                'secret' => '2d038e6d-07ae-4354-aebf-f924c198e9c2'
            ]);

            $authToken = $tokenResponse->json()['result'] ?? null;
            if (!$authToken) {
                $results[] = [
                    'shipping' => $shipment,
                    'tracking' => ['code' => 401, 'message' => 'Authentication failed'],
                ];
                continue;
            }

            // Get shipment details
            $detailsResponse = Http::get('https://api.trackingeyes.com/api/oceanbill/oceanBill', [
                'companyCode' => 100220,
                'token' => $authToken,
                'billId' => $shipment->subscription_id
            ]);

            $tracking = $detailsResponse->successful() ? $detailsResponse->json() : [];

            $results[] = [
                'shipping' => $shipment,
                'tracking' => $tracking,
            ];
        } catch (\Exception $e) {
            Log::error('Tracking Exception', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            $results[] = [
                'shipping' => $shipment,
                'tracking' => ['code' => 500, 'message' => 'Tracking failed'],
            ];
        }
    }

    return response()->json($results);
}

public function searchShippment(Request $request)
{
    $validator = Validator::make($request->all(), [
        'bill_no' => 'required|string',
        'carrier_code' => 'required|string',
    ]);
    if ($validator->fails()) {
        return response()->json([
            'code' => 422,
            'message' => 'Validation error: bill_no and carrier_code are required',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        // Get auth token
        $tokenResponse = Http::post('https://api.trackingeyes.com/api/auth/authorization', [
            'companyCode' => 100220,
            'secret' => '2d038e6d-07ae-4354-aebf-f924c198e9c2'
        ]);

        $authToken = $tokenResponse->json()['result'] ?? null;
        if (!$authToken) {
            return response()->json(['code' => 401, 'message' => 'Authentication failed'], 401);
        }

        // Search shipment
        $searchResponse = Http::withBody(json_encode([[
            'referenceNo' => $request->input('bill_no'),
            'blType' => 'BL',
            'carrierCd' => $request->input('carrier_code')
        ]]), 'application/json')->get('https://api.trackingeyes.com/api/oceanbill/batchOceanBill', [
            'companyCode' => 100220,
            'token' => $authToken
        ]);

        $result = $searchResponse->json();
        dd($result);
        if ($result['code'] !== 200 || empty($result['result'])) {
            return response()->json([
                'code' => 404,
                'message' => 'Shipment not found'
            ], 404);
        }

        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json([
            'code' => 500,
            'message' => 'Search failed: ' . $e->getMessage()
        ], 500);
    }
}




public function getTrackedShippingByID(Request $status, $id)
{
    $status = $status->query('status');
    $shippings = ReservedShipping::with([
        'user',
        'harborFrom',
        'harborTo',
        'container'
    ])->where('user_id', auth()->id());
    if (!is_null($status)) {
        $shippings->where('status', '!=', $status);
    }

    $shipment = $shippings->find($id);
    if (!$shipment) {
        return response()->json(['code' => 404, 'message' => 'Shipment not found'], 404);
    }

    try {
        if (!$shipment->track_number || !$shipment->carrier_code) {
            return response()->json([
                'shipping' => $shipment,
                'tracking' => [
                    'code' => 422,
                    'message' => 'Missing required tracking fields (track_number, carrier_code).',
                ],
            ]);
        }

        // Get auth token
        $tokenResponse = Http::post('https://api.trackingeyes.com/api/auth/authorization', [
            'companyCode' => 100220,
            'secret' => '2d038e6d-07ae-4354-aebf-f924c198e9c2'
        ]);

        $authToken = $tokenResponse->json()['result'] ?? null;
        if (!$authToken) {
            return response()->json([
                'shipping' => $shipment,
                'tracking' => ['code' => 401, 'message' => 'Authentication failed'],
            ]);
        }

        // Get detailed tracking
        $detailsResponse = Http::get('https://api.trackingeyes.com/api/oceanbill/oceanBill', [
            'companyCode' => 100220,
            'token' => $authToken,
            'billId' => $shipment->subscription_id
        ]);

        $tracking = $detailsResponse->successful() ? $detailsResponse->json() : [];

        // Get ship position if available
        $positionResponse = Http::get('https://api.trackingeyes.com/api/shipPosition/getShipPositionByBlno', [
            'companyCode' => 100220,
            'token' => $authToken,
            'referenceNo' => $shipment->track_number
        ]);

        $position = $positionResponse->successful() ? $positionResponse->json() : [];

        return response()->json([
            'shipping' => $shipment,
            'tracking' => $tracking,
            'position' => $position,
        ]);
    } catch (\Exception $e) {
        Log::error('Tracking Exception', [
            'shipment_id' => $shipment->id,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'shipping' => $shipment,
            'tracking' => ['code' => 500, 'message' => 'Tracking failed'],
        ]);
    }
}

}
