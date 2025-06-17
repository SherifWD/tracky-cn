<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReservedShipping;
use App\Models\User;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Auth;
use Illuminate\Support\Facades\Auth as FacadesAuth;

class AuthController extends Controller
{
     use HelpersTrait;
    use backendTraits;
public function loginValidateOtp(Request $request)
{
    $validator = Validator::make($request->all(), [
        'phone' => 'required|string',
        'country_code' => 'required|string',
        'otp' => 'required',
    ]);

    if ($validator->fails()) {
        return $this->returnValidationError('E001', $validator);
    }

    try {
        $user = User::where('phone', $request->phone)
            ->where('country_code', $request->country_code)
            ->where(function ($query) use ($request) {
                $query->where('otp', $request->otp)
                      ->orWhere('tmp_otp', $request->otp);
            })
            ->first();

        if (!$user) {
            return $this->returnError('E003', 'User not found. Please register.');
        }

        // Generate a random OTP
        $token = JWTAuth::fromUser($user);

        // ✅ Update shipments for this user that are not finalized (status ≠ 3)
        $shipments = ReservedShipping::where('user_id', $user->id)
            ->where('status', '!=', 3)
            ->get();

        foreach ($shipments as $shipment) {
            try {
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
                $authToken = $tokenResponse->json()['data'];

                // Prepare request headers
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

                // Determine new status code
                $statusCode = $shipment->status;
                if ($shipment->status === 0) {
                    $statusCode = 0;
                } elseif ($shipment->status === 3) {
                    $statusCode = 3;
                } elseif (
                    isset($portTracking['code']) && $portTracking['code'] === 200 &&
                    isset($portTracking['data']) && (!is_array($portTracking['data']) || empty($portTracking['data']))
                ) {
                    $statusCode = 1;
                } elseif (
                    isset($portTracking['code']) && $portTracking['code'] === 200 &&
                    isset($portTracking['data']) && is_array($portTracking['data']) && !empty($portTracking['data'])
                ) {
                    $statusCode = 2;
                }

                // Save the new status
                $shipment->status = $statusCode;
                $shipment->save();

            } catch (\Exception $ex) {
                \Log::error("Failed updating shipment ID {$shipment->id}: " . $ex->getMessage());
                continue;
            }
        }

        return $this->returnData('token', compact('token'), 'OTP validated successfully');
    } catch (\Exception $e) {
        \Log::error("Error in loginValidateOtp: " . $e->getMessage());
        return $this->returnError('E500', 'Failed to process request. Please try again.');
    }
}


public function validateOtp(Request $request)
{
    $validator = Validator::make($request->all(), [
        'phone' => 'required|string',
        'country_code' => 'required|string',
        'otp' => 'required|string',
    ]);

    if ($validator->fails()) {
        return $this->returnValidationError('E001', $validator);
    }

    try {
        $user = User::where('phone', $request->phone)
            ->where('country_code', $request->country_code)
            ->first();

        if (!$user) {
            return $this->returnError('E003', 'User not found. Please register.');
        }

        // Check OTP validity
        if ($user->otp === $request->otp && now()->lessThan($user->otp_expires_at)) {
            $user->otp = null; // Clear OTP after successful validation
            $user->save();

            $token = JWTAuth::fromUser($user);
            return $this->returnData('token', compact('token'), 'OTP validated successfully');
        }

        return $this->returnError('E002', 'Invalid or expired OTP');
    } catch (\Exception $e) {
        \Log::error("Error in validateOtp: " . $e->getMessage());
        return $this->returnError('E500', 'Failed to validate OTP. Please try again.');
    }
}
public function sendOtp($phone, $countryCode, $otp)
{
    $apiToken = env('ISEND_TOKEN');
    $apiUrl = "https://isend.com.ly/api/v3/sms/send";
    $senderId = env('ISEND_SENDER_ID', 'Fisaa App');

    $payload = [
        "recipient" => $countryCode . $phone,
        "sender_id" => $senderId,
        "type" => "plain",
        "message" => "Your OTP code is: $otp"
    ];

    $client = new \GuzzleHttp\Client();

    try {
        $response = $client->post($apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $responseData = json_decode($response->getBody()->getContents(), true);

        \Log::info('iSend API Response:', $responseData); // Log response for debugging

        // Always return the raw response for debugging or analysis
        return $responseData;

    } catch (\Exception $e) {
        \Log::error("Error sending OTP: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to send OTP. ' . $e->getMessage()
        ];
    }
}

 public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'country_code' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError('E001', $validator);
        }
        $otp = rand(10000, 99999);
        $user = User::firstOrCreate([
            'phone' => $request->phone,
            'country_code' => $request->country_code,
        ]);
        $user->otp = (string)$otp;
        $user->tmp_otp = (string)$otp;
        $user->save();
        $token = JWTAuth::fromUser($user);

        return $this->returnData('token', compact('token','user'), 'User registered successfully');
    }

public function updateProfile(Request $request){
    $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError('E001', $validator);
        }
    
    $user = JWTAuth::parseToken()->authenticate();
    if($request->otp){
        if($request->otp != $user->tmp_otp){
            return $this->returnError('404','Incorrect OTP');
        }
    }
    

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('images');
            $user->image = $imagePath;
        }
    $user->name = $request->name ?? $user->name;
    $user->phone = $request->phone ?? $user->phone;
    $user->country_code = $request->country_code ?? $user->country_code;
    $user->save();

    return $this->returnData('user', compact('user'), 'User updated successfully');

}

   


   public function updateFcmToken(Request $request)
    {
        try {

            $validate = Validator::make($request->all(), [
                'fcm'   => 'required',
            ]);

            if ($validate->fails()) {
                $code = $this->returnCodeAccordingToInput($validate);
                return $this->returnValidationError($code, $validate);
            }

            $id                = auth()->user()->id;
            $user              = User::find($id);
            $user['fcm'] = $request['fcm'];
            $user->save();
            return $this->returnSuccessMessage(__('app/public.successfully'));
        } catch (\Exception $ex) {
            return $this->returnError('404', $ex);
        }
    } // end of updateFcmToken


    public function getAuthenticatedUser()
    {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return $this->returnError('E003', 'User not found');
            }
        } catch (JWTException $e) {
            return $this->returnError('E500', 'Token error');
        }

        return $this->returnData('user', $user, 'Authenticated user retrieved successfully');
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return $this->returnSuccessMessage('User successfully logged out');
    }
}
