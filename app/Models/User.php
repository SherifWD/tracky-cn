<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements FilamentUser, JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    protected $guarded = [];

    protected $hidden = [
        'password',
        'temp_password',
        'temp_password_value',
        'remember_token',
        'otp',
        'tmp_otp',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'temp_password_value' => 'encrypted',
        'temp_password_expires_at' => 'datetime',
    ];

    public function generateTempPassword(int $ttlMinutes = 60): string
    {
        $password = Str::password(10, letters: true, numbers: true, symbols: false, spaces: false);

        $this->forceFill([
            'temp_password' => Hash::make($password),
            'temp_password_value' => $password,
            'temp_password_expires_at' => now()->addMinutes($ttlMinutes),
        ])->save();

        return $password;
    }

    public function isTempPasswordValid(string $password): bool
    {
        return filled($this->temp_password)
            && filled($this->temp_password_expires_at)
            && now()->lessThanOrEqualTo($this->temp_password_expires_at)
            && Hash::check($password, $this->temp_password);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return str_ends_with($this->email, '@tracky.com');
    }

    public function getPathAttribute($val)
    {
        return ($val !== null) ? asset('/'.$val) : '';

    }
}
