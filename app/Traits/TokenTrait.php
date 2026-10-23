<?php

namespace App\Traits;

use App\Models\Auth\AppUser;
use App\Models\System\User;

trait TokenTrait
{
    /** Access Token 有效期（天） */
    protected const ACCESS_TOKEN_TTL_DAYS = 1;

    /** Refresh Token 有效期（天） */
    protected const REFRESH_TOKEN_TTL_DAYS = 7;

    /**
     * 签发 Access Token + Refresh Token
     */
    protected function issueTokens(AppUser|User $user): array
    {
        $accessExpiresAt = now()->addDays(self::ACCESS_TOKEN_TTL_DAYS);
        $refreshExpiresAt = now()->addDays(self::REFRESH_TOKEN_TTL_DAYS);

        return [
            'access_token' => $user->createToken('access_token', ['access-api'], $accessExpiresAt)->plainTextToken,
            'refresh_token' => $user->createToken('refresh_token', ['refresh-token'], $refreshExpiresAt)->plainTextToken,
            'expires_at' => $accessExpiresAt->timestamp,
            'refresh_expires_at' => $refreshExpiresAt->timestamp,
        ];
    }
}
