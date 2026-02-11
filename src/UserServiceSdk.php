<?php

namespace Jinom\UserServiceSdk;

use Jinom\UserServiceSdk\Services\TokenManager;
use Jinom\UserServiceSdk\Services\UserServiceClient;
use Jinom\UserServiceSdk\Services\UserSyncService;

/**
 * Main entry point for User Service SDK
 *
 * Provides a unified interface for:
 * - Token management (store, refresh, retrieve)
 * - User synchronization to User Service
 * - User Service API operations
 */
class UserServiceSdk
{
    public function __construct(
        protected TokenManager $tokenManager,
        protected UserServiceClient $userServiceClient,
        protected UserSyncService $userSyncService
    ) {}

    // ========================================
    // Token Management
    // ========================================

    /**
     * Store tokens from Keycloak callback
     */
    public function storeTokens(int|string $userId, array $tokenData): void
    {
        $this->tokenManager->storeTokens($userId, $tokenData);
    }

    /**
     * Get a valid access token (auto-refreshes if expired)
     */
    public function getValidToken(int|string $userId): ?string
    {
        return $this->tokenManager->getValidToken($userId);
    }

    /**
     * Refresh the access token
     */
    public function refreshToken(int|string $userId, string $refreshToken): ?string
    {
        return $this->tokenManager->refreshToken($userId, $refreshToken);
    }

    /**
     * Clear all tokens for a user
     */
    public function clearTokens(int|string $userId): void
    {
        $this->tokenManager->clearTokens($userId);
    }

    /**
     * Check if user has valid tokens
     */
    public function hasValidTokens(int|string $userId): bool
    {
        return $this->tokenManager->hasValidTokens($userId);
    }

    /**
     * Get all token data for a user
     */
    public function getTokenData(int|string $userId): ?array
    {
        return $this->tokenManager->getTokenData($userId);
    }

    /**
     * Introspect token with Keycloak
     */
    public function introspectToken(string $token): ?array
    {
        return $this->tokenManager->introspectToken($token);
    }

    // ========================================
    // User Sync
    // ========================================

    /**
     * Sync user to User Service (async via queue)
     *
     * @param  object|array  $keycloakUser  Keycloak user data from Socialite
     * @param  object|array  $localUser  Local user model
     * @param  array  $tokenData  Token data to store
     */
    public function syncUser(object|array $keycloakUser, object|array $localUser, array $tokenData = []): void
    {
        $this->userSyncService->syncUser($keycloakUser, $localUser, $tokenData);
    }

    /**
     * Sync user directly (synchronous)
     */
    public function syncUserDirectly(int|string $localUserId, string $keycloakSub, array $userData): bool
    {
        return $this->userSyncService->syncUserDirectly($localUserId, $keycloakSub, $userData);
    }

    // ========================================
    // User Service Client
    // ========================================

    /**
     * Create user in User Service
     */
    public function createUser(int|string $localUserId, array $userData): array
    {
        return $this->userServiceClient->createUser($localUserId, $userData);
    }

    /**
     * Update user in User Service
     */
    public function updateUser(int|string $localUserId, string $userId, array $userData): array
    {
        return $this->userServiceClient->updateUser($localUserId, $userId, $userData);
    }

    /**
     * Find user by Keycloak Sub ID
     */
    public function findByKeycloakId(int|string $localUserId, string $keycloakSub): ?array
    {
        return $this->userServiceClient->findByKeycloakId($localUserId, $keycloakSub);
    }

    /**
     * Check if user exists in User Service
     */
    public function userExists(int|string $localUserId, string $keycloakSub): bool
    {
        return $this->userServiceClient->userExists($localUserId, $keycloakSub);
    }

    /**
     * Get user from User Service
     */
    public function getUser(int|string $localUserId, string $userId): ?array
    {
        return $this->userServiceClient->getUser($localUserId, $userId);
    }

    /**
     * Delete user from User Service
     */
    public function deleteUser(int|string $localUserId, string $userId): bool
    {
        return $this->userServiceClient->deleteUser($localUserId, $userId);
    }

    /**
     * Create or update user (upsert)
     */
    public function createOrUpdateUser(int|string $localUserId, string $keycloakSub, array $userData): array
    {
        return $this->userServiceClient->createOrUpdateUser($localUserId, $keycloakSub, $userData);
    }

    // ========================================
    // Service Access
    // ========================================

    /**
     * Get Token Manager instance
     */
    public function tokenManager(): TokenManager
    {
        return $this->tokenManager;
    }

    /**
     * Get User Service Client instance
     */
    public function userServiceClient(): UserServiceClient
    {
        return $this->userServiceClient;
    }

    /**
     * Get User Sync Service instance
     */
    public function userSyncService(): UserSyncService
    {
        return $this->userSyncService;
    }
}
