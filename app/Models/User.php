<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Laravel\Sanctum\HasApiTokens;
use Filament\Panel;
class User extends Authenticatable implements JWTSubject,FilamentUser
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
protected $guarded =[];

public function canAccessPanel(Panel $panel): bool
    {
        return str_ends_with($this->email, '@tracky.com');
    }
 public function getImageAttribute($val)
    {
        return ($val !== null) ? asset('/'.$val) : "";

    }
}
