<?php

namespace Jinom\UserServiceSdk\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Jinom\UserServiceSdk\Contracts\TokenManagerInterface;
use Jinom\UserServiceSdk\Exceptions\TokenRefreshException;

class TokenManager implements TokenManagerInterface
{
    private string $tokenUrl;
    private string $clientId;
    private string $clientSecret;
    private string $cachePrefix;
    private int $cacheTtl;
    private int $bufferSeconds;

    public function __construct()
    {
        $baseUrl = config('user-service-sdk.keycloak.base_url');
        $realm = config('user-service-sdk.keycloak.realm');

        $this->tokenUrl = rtrim($baseUrl, '/') . "/realms/{$realm}/protocol/openid-connect/token";
        $this->clientId = config('user-service-sdk.keycloak.client_id');
        $this->clientSecret = config('user-service-sdk.keycloak.client_secret');
        $this->cachePrefix = config('user-service-sdk.token.cache_prefix', 'user_service_tokens');
        $this->cacheTtl = config('user-service-sdk.token.cache_ttl', 60 * 60 * 24 * 30);
        $this->bufferSeconds = config('user-service-sdk.token.buffer_seconds', 30);
    }

    /**
     * Store tokens from Keycloak callback
     */
    public function storeTokens(int|string $userId, array $tokenData): void
    {
        $expiresIn = $tokenData['expires_in'] ?? $tokenData['expiresIn'] ?? 300;

        $data = [
            'access_token' => $tokenData['access_token'] ?? $tokenData['token'] ?? null,
            'refresh_token' => $tokenData['refresh_token'] ?? $tokenData['refreshToken'] ?? null,
            'id_token' => $tokenData['id_token'] ?? null,
            'expires_at' => now()->addSeconds($expiresIn - $this->bufferSeconds)->timestamp,
            'keycloak_id' => $tokenData['keycloak_id'] ?? $tokenData['sub'] ?? null,
        ];

        Cache::put(
            $this->getCacheKey($userId),
            $data,
            now()->addSeconds($this->cacheTtl)
        );

        Log::debug('UserServiceSdk: Tokens stored', [
            'user_id' => $userId,
            'expires_at' => $data['expires_at'],
        ]);
    }

    /**
     * Get a valid access token (auto-refresh if expired)
     */
    public function getValidToken(int|string $userId): ?string
    {
        $tokenData = $this->getTokenData($userId);

        if (!$tokenData) {
            Log::warning('UserServiceSdk: No tokens found', ['user_id' => $userId]);
            return null;
        }

        // Check if token is expired or about to expire
        if ($this->isTokenExpired($tokenData['expires_at'])) {
            Log::info('UserServiceSdk: Token expired, attempting refresh', ['user_id' => $userId]);

            if (empty($tokenData['refresh_token'])) {
                Log::error('UserServiceSdk: No refresh token available', ['user_id' => $userId]);
                return null;
            }

            return $this->refreshToken($userId, $tokenData['refresh_token']);
        }

        return $tokenData['access_token'];
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshToken(int|string $userId, string $refreshToken): ?string
    {
        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post($this->tokenUrl, [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshToken,
                ]);

            if (!$response->successful()) {
                $errorDescription = $response->json('error_description') ?? $response->body();

                Log::error('UserServiceSdk: Token refresh failed', [
                    'user_id' => $userId,
                    'status' => $response->status(),
                    'error' => $errorDescription,
                ]);

                // Clear invalid tokens
                $this->clearTokens($userId);

                throw TokenRefreshException::keycloakError($userId, $errorDescription, $response->status());
            }

            $data = $response->json();

            // Store the new tokens
            $this->storeTokens($userId, [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $refreshToken,
                'id_token' => $data['id_token'] ?? null,
                'expires_in' => $data['expires_in'] ?? 300,
                'keycloak_id' => $this->getTokenData($userId)['keycloak_id'] ?? null,
            ]);

            Log::info('UserServiceSdk: Token refreshed successfully', ['user_id' => $userId]);

            return $data['access_token'];

        } catch (TokenRefreshException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('UserServiceSdk: Token refresh exception', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            $this->clearTokens($userId);
            return null;
        }
    }

    /**
     * Clear all tokens for a user
     */
    public function clearTokens(int|string $userId): void
    {
        Cache::forget($this->getCacheKey($userId));

        Log::debug('UserServiceSdk: Tokens cleared', ['user_id' => $userId]);
    }

    /**
     * Get all token data for a user
     */
    public function getTokenData(int|string $userId): ?array
    {
        return Cache::get($this->getCacheKey($userId));
    }

    /**
     * Check if user has valid tokens
     */
    public function hasValidTokens(int|string $userId): bool
    {
        $tokenData = $this->getTokenData($userId);

        if (!$tokenData) {
            return false;
        }

        // If access token expired, check if refresh token is available
        if ($this->isTokenExpired($tokenData['expires_at'])) {
            return !empty($tokenData['refresh_token']);
        }

        return true;
    }

    /**
     * Check if token is expired
     */
    private function isTokenExpired(int $expiresAt): bool
    {
        return now()->timestamp >= $expiresAt;
    }

    /**
     * Get cache key for user tokens
     */
    private function getCacheKey(int|string $userId): string
    {
        return "{$this->cachePrefix}:{$userId}";
    }

    /**
     * Introspect token to verify it's still valid with Keycloak
     */
    public function introspectToken(string $token): ?array
    {
        try {
            $baseUrl = config('user-service-sdk.keycloak.base_url');
            $realm = config('user-service-sdk.keycloak.realm');
            $introspectUrl = rtrim($baseUrl, '/') . "/realms/{$realm}/protocol/openid-connect/token/introspect";

            $response = Http::asForm()
                ->timeout(30)
                ->post($introspectUrl, [
                    'token' => $token,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['active'] ? $data : null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('UserServiceSdk: Token introspection failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
