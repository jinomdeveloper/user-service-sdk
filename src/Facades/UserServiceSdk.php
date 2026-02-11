<?php

namespace Jinom\UserServiceSdk\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void storeTokens(int|string $userId, array $tokenData)
 * @method static string|null getValidToken(int|string $userId)
 * @method static string|null refreshToken(int|string $userId, string $refreshToken)
 * @method static void clearTokens(int|string $userId)
 * @method static bool hasValidTokens(int|string $userId)
 * @method static array|null getTokenData(int|string $userId)
 * @method static array|null introspectToken(string $token)
 * @method static void syncUser(object|array $keycloakUser, object|array $localUser, array $tokenData = [])
 * @method static bool syncUserDirectly(int|string $localUserId, string $keycloakSub, array $userData)
 * @method static array createUser(int|string $localUserId, array $userData)
 * @method static array updateUser(int|string $localUserId, string $userId, array $userData)
 * @method static array|null findByKeycloakId(int|string $localUserId, string $keycloakSub)
 * @method static bool userExists(int|string $localUserId, string $keycloakSub)
 * @method static array|null getUser(int|string $localUserId, string $userId)
 * @method static bool deleteUser(int|string $localUserId, string $userId)
 * @method static array createOrUpdateUser(int|string $localUserId, string $keycloakSub, array $userData)
 * @method static \Jinom\UserServiceSdk\Services\TokenManager tokenManager()
 * @method static \Jinom\UserServiceSdk\Services\UserServiceClient userServiceClient()
 * @method static \Jinom\UserServiceSdk\Services\UserSyncService userSyncService()
 *
 * @see \Jinom\UserServiceSdk\UserServiceSdk
 */
class UserServiceSdk extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Jinom\UserServiceSdk\UserServiceSdk::class;
    }
}
