<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwilioSmsOtpService;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use backendTraits;
    use HelpersTrait;

    public function __construct(private TwilioSmsOtpService $otpSender) {}

    public function login(Request $request)
    {
        $tempPassword = $this->tempPasswordFromRequest($request);

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'country_code' => [$tempPassword === null ? 'required' : 'nullable', 'string'],
            'password' => 'nullable|string',
            'temp_password' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError('E001', $validator);
        }

        $phone = (string) $request->phone;
        $countryCode = (string) $request->country_code;

        if ($tempPassword !== null) {
            return $this->tempPasswordLoginResponse($phone, $countryCode, $tempPassword);
        }

        $user = $this->findUserByPhone($phone, $countryCode);

        if ($user) {
            return $this->sendOtpResponse($user, 'OTP sent successfully', $phone, $countryCode);
        }

        return $this->sendPendingOtpResponse(
            $phone,
            $countryCode,
            'OTP sent successfully'
        );
    }

    public function loginValidateOtp(Request $request)
    {
        return $this->validateOtp($request);
    }

    public function validateOtp(Request $request)
    {
        $tempPassword = $this->tempPasswordFromRequest($request);
        $hasOtp = filled($request->input('otp'));

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'country_code' => [$tempPassword !== null && ! $hasOtp ? 'nullable' : 'required', 'string'],
            'otp' => 'required_without_all:password,temp_password|nullable|string',
            'password' => 'required_without_all:otp,temp_password|nullable|string',
            'temp_password' => 'required_without_all:otp,password|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->returnValidationError('E001', $validator);
        }

        try {
            $phone = (string) $request->phone;
            $countryCode = (string) $request->country_code;

            if (! $hasOtp && $tempPassword !== null) {
                return $this->tempPasswordLoginResponse($phone, $countryCode, $tempPassword);
            }

            $otp = $this->normalizeOtp((string) $request->otp);
            $user = $this->findUserByPhone($phone, $countryCode);

            if ($user && $this->isUserOtpValid($user, $otp)) {
                $user->otp = null;
                $user->otp_expires_at = null;
                $user->save();

                $token = JWTAuth::fromUser($user);

                return $this->tokenResponse($user, $token, 'OTP validated successfully');
            }

            if ($this->isPendingOtpValid($phone, $countryCode, $otp)) {
                Cache::forget($this->pendingOtpCacheKey($phone, $countryCode));

                $user ??= $this->firstOrCreateUserByPhone($phone, $countryCode);

                $token = JWTAuth::fromUser($user);

                return $this->tokenResponse($user, $token, 'OTP validated successfully');
            }

            return $this->returnError('E002', 'Invalid or expired OTP');
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

        $token = JWTAuth::getToken()?->get();

        return $this->tokenResponse($user, (string) $token, 'Authenticated user retrieved successfully');
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return $this->returnSuccessMessage('User successfully logged out');
    }

    private function sendOtpResponse(User $user, string $message, ?string $phone = null, ?string $countryCode = null)
    {
        $otp = (string) random_int(10000, 99999);
        $user->otp = $otp;
        $user->tmp_otp = $otp;
        $user->otp_expires_at = now()->addMinutes((int) config('services.twilio.otp_ttl', 10));
        $user->save();

        try {
            $this->sendOtp($phone ?? $user->phone, $countryCode ?? $user->country_code, $otp);
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

    private function sendPendingOtpResponse(string $phone, string $countryCode, string $message)
    {
        $otp = (string) random_int(10000, 99999);
        $expiresAt = now()->addMinutes((int) config('services.twilio.otp_ttl', 10));
        $cacheKey = $this->pendingOtpCacheKey($phone, $countryCode);

        Cache::put($cacheKey, [
            'otp' => $otp,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        try {
            $this->sendOtp($phone, $countryCode, $otp);
        } catch (Throwable $e) {
            Cache::forget($cacheKey);

            Log::error('Error sending OTP: '.$e->getMessage());

            return $this->returnError('E500', 'Failed to send OTP. Please try again.');
        }

        return $this->returnData('user', [
            'user' => null,
            'phone' => $phone,
            'country_code' => $countryCode,
            'otp' => $otp,
            'otp_expires_at' => $expiresAt->toIso8601String(),
        ], $message);
    }

    private function isUserOtpValid(User $user, string $otp): bool
    {
        return $user->otp === $otp
            && filled($user->otp_expires_at)
            && now()->lessThanOrEqualTo($user->otp_expires_at);
    }

    private function isPendingOtpValid(string $phone, string $countryCode, string $otp): bool
    {
        $pendingOtp = Cache::get($this->pendingOtpCacheKey($phone, $countryCode));

        return is_array($pendingOtp)
            && ($pendingOtp['otp'] ?? null) === $otp
            && filled($pendingOtp['expires_at'] ?? null)
            && now()->lessThanOrEqualTo($pendingOtp['expires_at']);
    }

    private function pendingOtpCacheKey(string $phone, string $countryCode): string
    {
        return 'auth:pending-otp:'.sha1($this->normalizeFullPhoneNumber($phone, $countryCode));
    }

    private function tokenResponse(User $user, string $token, string $message)
    {
        return $this->returnData('token', compact('token', 'user'), $message);
    }

    private function tempPasswordLoginResponse(string $phone, ?string $countryCode, string $password)
    {
        try {
            $user = $this->findUserByTempPasswordCredentials($phone, $countryCode, $password);

            if (! $user) {
                return $this->returnError('E002', 'Invalid or expired temporary password');
            }

            $token = JWTAuth::fromUser($user);

            return $this->tokenResponse($user, $token, 'Temporary password validated successfully');
        } catch (Throwable $e) {
            Log::error('Error in tempPasswordLoginResponse: '.$e->getMessage());

            return $this->returnError('E500', 'Failed to validate temporary password. Please try again.');
        }
    }

    private function findUserByTempPasswordCredentials(string $phone, ?string $countryCode, string $password): ?User
    {
        return $this->tempPasswordCandidates($phone, $countryCode)
            ->first(fn (User $user): bool => $user->isTempPasswordValid($password));
    }

    private function tempPasswordCandidates(string $phone, ?string $countryCode)
    {
        if (filled($countryCode)) {
            $user = $this->findUserByPhone($phone, $countryCode);

            return collect($user ? [$user] : []);
        }

        return User::whereNotNull('temp_password')
            ->get()
            ->filter(fn (User $user): bool => $this->phoneMatchesForTempPassword($user, $phone))
            ->values();
    }

    private function phoneMatchesForTempPassword(User $user, string $phone): bool
    {
        $phone = trim($phone);
        $phoneDigits = preg_replace('/\D+/', '', $phone);
        $phoneVariants = $this->phoneVariants((string) $user->phone, (string) $user->country_code);

        if (in_array($phone, $phoneVariants, true) || in_array($phoneDigits, $phoneVariants, true)) {
            return true;
        }

        if ($phoneDigits === '') {
            return false;
        }

        $userFullDigits = preg_replace(
            '/\D+/',
            '',
            $this->normalizeFullPhoneNumber((string) $user->phone, (string) $user->country_code)
        );

        return $userFullDigits === $phoneDigits;
    }

    private function findUserByPhone(string $phone, string $countryCode): ?User
    {
        $phone = trim($phone);
        $countryCode = trim($countryCode);

        $user = User::where('phone', $phone)
            ->where('country_code', $countryCode)
            ->first();

        if ($user) {
            return $user;
        }

        $phoneVariants = $this->phoneVariants($phone, $countryCode);
        $countryCodeVariants = $this->countryCodeVariants($countryCode);

        return User::whereIn('phone', $phoneVariants)
            ->whereIn('country_code', $countryCodeVariants)
            ->first();
    }

    private function firstOrCreateUserByPhone(string $phone, string $countryCode): User
    {
        $phone = trim($phone);
        $countryCode = trim($countryCode);

        $user = $this->findUserByPhone($phone, $countryCode);

        if ($user) {
            return $user;
        }

        return User::firstOrCreate([
            'phone' => $phone,
            'country_code' => $countryCode,
        ]);
    }

    private function phoneVariants(string $phone, string $countryCode): array
    {
        $phone = trim($phone);
        $phoneDigits = preg_replace('/\D+/', '', $phone);
        $countryDigits = preg_replace('/\D+/', '', $countryCode);
        $localDigits = $phoneDigits;

        if ($countryDigits !== '' && str_starts_with($phoneDigits, $countryDigits)) {
            $localDigits = substr($phoneDigits, strlen($countryDigits));
        }

        $localWithoutLeadingZero = ltrim($localDigits, '0');

        return array_values(array_filter(array_unique([
            $phone,
            $phoneDigits,
            '+'.$phoneDigits,
            $localDigits,
            $localWithoutLeadingZero,
            '0'.$localWithoutLeadingZero,
        ])));
    }

    private function countryCodeVariants(string $countryCode): array
    {
        $countryCode = trim($countryCode);
        $countryDigits = preg_replace('/\D+/', '', $countryCode);

        return array_values(array_filter(array_unique([
            $countryCode,
            $countryDigits,
            '+'.$countryDigits,
        ])));
    }

    private function normalizeFullPhoneNumber(string $phone, string $countryCode): string
    {
        $phoneDigits = preg_replace('/\D+/', '', trim($phone));
        $countryDigits = preg_replace('/\D+/', '', trim($countryCode));

        if ($countryDigits !== '' && str_starts_with($phoneDigits, $countryDigits)) {
            return '+'.$phoneDigits;
        }

        return '+'.$countryDigits.ltrim($phoneDigits, '0');
    }

    private function normalizeOtp(string $otp): string
    {
        return preg_replace('/\D+/', '', trim($otp));
    }

    private function tempPasswordFromRequest(Request $request): ?string
    {
        foreach (['password', 'temp_password'] as $field) {
            if ($request->filled($field)) {
                return trim((string) $request->input($field));
            }
        }

        return null;
    }
}
