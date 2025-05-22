<?php

namespace App\Http\Api\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    
public function subscribeToShipmentTracking(Request $request)
{
    $validated = $request->validate([
        'billNo' => 'nullable|string',
        'containerNo' => 'nullable|string',
        'carrierCode' => 'required|string',
        'portCode' => 'nullable|string',
        'isExport' => 'nullable|in:E,I',
        'dataType' => 'nullable|array',
    ]);

    if (empty($validated['billNo']) && empty($validated['containerNo'])) {
        return response()->json(['error' => 'Either billNo or containerNo must be provided.'], 422);
    }

    $payload = array_filter([
        'billNo' => $validated['billNo'] ?? '',
        'containerNo' => $validated['containerNo'] ?? '',
        'carrierCode' => $validated['carrierCode'],
        'portCode' => $validated['portCode'] ?? '',
        'isExport' => $validated['isExport'] ?? 'E',
        'dataType' => $validated['dataType'] ?? ['CARRIER'],
    ]);

    // Create signValue (HMAC SHA256 of the payload using your secret)
    $secret = config('services.ocean_tracking.secret');
    $signValue = base64_encode(hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $secret, true));

    // Send the POST request
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
        'appId' => config('services.ocean_tracking.app_id'),
        'signValue' => $signValue,
    ])->post(config('services.ocean_tracking.url'), $payload);

    // Return the response
    return response()->json([
        'status' => $response->status(),
        'data' => $response->json(),
    ]);
}

}
