<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwilioWhatsAppOtpService;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use backendTraits;
    use HelpersTrait;

    public function __construct(private TwilioWhatsAppOtpService $otpSender) {}

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'country_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError('E001', $validator);
        }

        $user = User::where('phone', $request->phone)
            ->where('country_code', $request->country_code)
            ->first();

        if (! $user) {
            return $this->returnError('E003', 'User not found. Please register.');
        }

        return $this->sendOtpResponse($user, 'OTP sent successfully');
    }

    public function loginValidateOtp(Request $request)
    {
        return $this->validateOtp($request);
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

            if (! $user) {
                return $this->returnError('E003', 'User not found. Please register.');
            }

            if (
                $user->otp !== (string) $request->otp
                || blank($user->otp_expires_at)
                || now()->greaterThan($user->otp_expires_at)
            ) {
                return $this->returnError('E002', 'Invalid or expired OTP');
            }

            $user->otp = null;
            $user->otp_expires_at = null;
            $user->save();

            $token = JWTAuth::fromUser($user);

            return $this->returnData('token', compact('token', 'user'), 'OTP validated successfully');
        } catch (Throwable $e) {
            Log::error('Error in validateOtp: '.$e->getMessage());

            return $this->returnError('E500', 'Failed to validate OTP. Please try again.');
        }
    }

    public function sendOtp($phone, $countryCode, $otp)
    {
        return $this->otpSender->send((string) $phone, (string) $countryCode, (string) $otp);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'country_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError('E001', $validator);
        }

        $user = User::firstOrCreate([
            'phone' => $request->phone,
            'country_code' => $request->country_code,
        ]);

        return $this->sendOtpResponse($user, 'User registered successfully. OTP sent successfully');
    }

    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError('E001', $validator);
        }

        $user = JWTAuth::parseToken()->authenticate();

        if ($request->otp) {
            if ($request->otp != $user->tmp_otp) {
                return $this->returnError('404', 'Incorrect OTP');
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
                'fcm' => 'required',
            ]);

            if ($validate->fails()) {
                $code = $this->returnCodeAccordingToInput($validate);

                return $this->returnValidationError($code, $validate);
            }

            $id = auth()->user()->id;
            $user = User::find($id);
            $user['fcm'] = $request['fcm'];
            $user->save();

            return $this->returnSuccessMessage(__('app/public.successfully'));
        } catch (Throwable $ex) {
            return $this->returnError('404', $ex);
        }
    }

    public function getAuthenticatedUser()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
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

    private function sendOtpResponse(User $user, string $message)
    {
        $otp = (string) random_int(10000, 99999);
        $user->otp = $otp;
        $user->tmp_otp = $otp;
        $user->otp_expires_at = now()->addMinutes((int) config('services.twilio.otp_ttl', 10));
        $user->save();

        try {
            $this->sendOtp($user->phone, $user->country_code, $otp);
        } catch (Throwable $e) {
            $user->otp = null;
            $user->tmp_otp = null;
            $user->otp_expires_at = null;
            $user->save();

            Log::error('Error sending OTP: '.$e->getMessage());

            return $this->returnError('E500', 'Failed to send OTP. Please try again.');
        }

        $user->refresh();

        return $this->returnData('user', [
            'user' => $user,
            'otp_expires_at' => $user->otp_expires_at?->toIso8601String(),
        ], $message);
    }
}
