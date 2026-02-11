<?php

namespace Jinom\UserServiceSdk\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Jinom\UserServiceSdk\Exceptions\UserServiceException;

class UserServiceClient
{
    private string $baseUrl;

    private int $timeout;

    public function __construct(
        private TokenManager $tokenManager
    ) {
        $this->baseUrl = rtrim(config('user-service-sdk.user_service.base_url', ''), '/');
        $this->timeout = config('user-service-sdk.user_service.timeout', 30);
    }

    /**
     * Create user di User Service dengan Keycloak Bearer Token
     */
    public function createUser(int|string $localUserId, array $userData): array
    {
        $token = $this->getTokenOrFail($localUserId);

        Log::debug('UserServiceSdk: Creating user in User Service', [
            'local_user_id' => $localUserId,
            'user_data' => array_keys($userData),
        ]);

        $response = Http::withToken($token)
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/api/v1/users/register", $userData);

        if (! $response->successful()) {
            throw UserServiceException::httpError($response->status(), $response->body());
        }

        Log::info('UserServiceSdk: User created in User Service', [
            'local_user_id' => $localUserId,
            'response' => $response->json(),
        ]);

        return $response->json();
    }

    /**
     * Update user di User Service
     */
    public function updateUser(int|string $localUserId, string $userId, array $userData): array
    {
        $token = $this->getTokenOrFail($localUserId);

        Log::debug('UserServiceSdk: Updating user in User Service', [
            'local_user_id' => $localUserId,
            'user_service_id' => $userId,
        ]);

        $response = Http::withToken($token)
            ->timeout($this->timeout)
            ->patch("{$this->baseUrl}/api/v1/users-management/{$userId}", $userData);

        if (! $response->successful()) {
            throw UserServiceException::httpError($response->status(), $response->body());
        }

        Log::info('UserServiceSdk: User updated in User Service', [
            'local_user_id' => $localUserId,
            'user_service_id' => $userId,
        ]);

        return $response->json();
    }

    /**
     * Find user by Keycloak Sub ID
     */
    public function findByKeycloakId(int|string $localUserId, string $keycloakSub): ?array
    {
        $token = $this->tokenManager->getValidToken($localUserId);

        if (! $token) {
            Log::warning('UserServiceSdk: No token for findByKeycloakId', [
                'local_user_id' => $localUserId,
            ]);

            return null;
        }

        $response = Http::withToken($token)
            ->timeout($this->timeout)
            ->get("{$this->baseUrl}/api/v1/users-management/keycloak/{$keycloakSub}");

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw UserServiceException::httpError($response->status(), $response->body());
        }

        return $response->json();
    }

    /**
     * Check if user exists in User Service
     */
    public function userExists(int|string $localUserId, string $keycloakSub): bool
    {
        try {
            $user = $this->findByKeycloakId($localUserId, $keycloakSub);

            return $user !== null;
        } catch (UserServiceException $e) {
            if ($e->getCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Get user by ID from User Service
     */
    public function getUser(int|string $localUserId, string $userId): ?array
    {
        $token = $this->getTokenOrFail($localUserId);

        $response = Http::withToken($token)
            ->timeout($this->timeout)
            ->get("{$this->baseUrl}/api/v1/users-management/{$userId}");

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw UserServiceException::httpError($response->status(), $response->body());
        }

        return $response->json();
    }

    /**
     * Delete user from User Service
     */
    public function deleteUser(int|string $localUserId, string $userId): bool
    {
        $token = $this->getTokenOrFail($localUserId);

        $response = Http::withToken($token)
            ->timeout($this->timeout)
            ->delete("{$this->baseUrl}/api/v1/users-management/{$userId}");

        if (! $response->successful() && $response->status() !== 404) {
            throw UserServiceException::httpError($response->status(), $response->body());
        }

        return true;
    }

    /**
     * Create or update user (upsert)
     */
    public function createOrUpdateUser(int|string $localUserId, string $keycloakSub, array $userData): array
    {
        $existingUser = $this->findByKeycloakId($localUserId, $keycloakSub);
        $registrationEnabled = config('user-service-sdk.sync.registration_enabled', true);

        if ($existingUser) {
            $userId = $existingUser['id'] ?? $existingUser['data']['id'] ?? null;
            if ($userId) {
                return $this->updateUser($localUserId, $userId, $userData);
            }
        }

        if (! $registrationEnabled) {
            throw UserServiceException::userNotFound($keycloakSub);
        }

        return $this->createUser($localUserId, $userData);
    }

    /**
     * Get valid token or throw exception
     */
    private function getTokenOrFail(int|string $localUserId): string
    {
        $token = $this->tokenManager->getValidToken($localUserId);

        if (! $token) {
            throw UserServiceException::noToken();
        }

        return $token;
    }

    /**
     * Set custom base URL (useful for testing)
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = rtrim($baseUrl, '/');

        return $this;
    }
}
