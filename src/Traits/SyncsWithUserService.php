<?php

namespace Jinom\UserServiceSdk\Traits;

use Jinom\UserServiceSdk\Facades\UserServiceSdk;

/**
 * Trait for easily syncing users to User Service after Keycloak login.
 * 
 * Usage in LoginController:
 * 
 * use Jinom\UserServiceSdk\Traits\SyncsWithUserService;
 * 
 * class LoginController extends Controller
 * {
 *     use SyncsWithUserService;
 * 
 *     public function handleCallback()
 *     {
 *         $keycloakUser = Socialite::driver('keycloak')->user();
 *         $user = User::updateOrCreate([...]);
 *         
 *         $this->syncToUserService($keycloakUser, $user);
 *         
 *         // or with token data
 *         $this->syncToUserService($keycloakUser, $user, [
 *             'access_token' => $keycloakUser->token,
 *             'refresh_token' => $keycloakUser->refreshToken,
 *             'expires_in' => $keycloakUser->expiresIn,
 *         ]);
 *     }
 * }
 */
trait SyncsWithUserService
{
    /**
     * Sync user to User Service after Keycloak login
     *
     * @param object|array $keycloakUser The Keycloak user data from Socialite
     * @param object|array $localUser The local user model
     * @param array $tokenData Optional token data (if not provided, extracts from keycloakUser)
     */
    protected function syncToUserService(object|array $keycloakUser, object|array $localUser, array $tokenData = []): void
    {
        // Extract token data from Socialite user if not provided
        if (empty($tokenData) && is_object($keycloakUser)) {
            $tokenData = [
                'access_token' => $keycloakUser->token ?? null,
                'refresh_token' => $keycloakUser->refreshToken ?? null,
                'id_token' => $keycloakUser->user['id_token'] ?? null,
                'expires_in' => $keycloakUser->expiresIn ?? 300,
            ];
        }

        UserServiceSdk::syncUser($keycloakUser, $localUser, $tokenData);
    }

    /**
     * Store tokens for later use
     *
     * @param int|string $userId Local user ID
     * @param array $tokenData Token data from Keycloak
     */
    protected function storeKeycloakTokens(int|string $userId, array $tokenData): void
    {
        UserServiceSdk::storeTokens($userId, $tokenData);
    }

    /**
     * Get a valid access token (auto-refreshes if needed)
     *
     * @param int|string $userId Local user ID
     * @return string|null
     */
    protected function getKeycloakToken(int|string $userId): ?string
    {
        return UserServiceSdk::getValidToken($userId);
    }

    /**
     * Clear all tokens for a user (e.g., on logout)
     *
     * @param int|string $userId Local user ID
     */
    protected function clearKeycloakTokens(int|string $userId): void
    {
        UserServiceSdk::clearTokens($userId);
    }
}
