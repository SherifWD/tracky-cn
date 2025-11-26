<?php

return array_merge(
    require base_path('vendor/tymon/jwt-auth/config/config.php'),
    [
        /*
        |--------------------------------------------------------------------------
        | Never expiring tokens
        |--------------------------------------------------------------------------
        |
        | Setting the TTL to null makes the issued tokens effectively "infinite".
        | Since exp is no longer required, we remove it from the required claims.
        |
        */
        'ttl' => env('JWT_TTL', null),

        'required_claims' => [
            'iss',
            'iat',
            'nbf',
            'sub',
            'jti',
        ],
    ]
);
