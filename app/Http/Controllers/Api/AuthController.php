<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;
use Illuminate\Http\Request;
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
