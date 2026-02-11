<?php

namespace Jinom\UserServiceSdk\Services;

use Illuminate\Support\Facades\Log;
use Jinom\UserServiceSdk\Events\UserSyncFailed;
use Jinom\UserServiceSdk\Events\UserSyncSucceeded;
use Jinom\UserServiceSdk\Exceptions\UserServiceException;
use Jinom\UserServiceSdk\Jobs\SyncUserToUserServiceJob;

class UserSyncService
{
    public function __construct(
        private TokenManager $tokenManager,
        private UserServiceClient $userServiceClient
    ) {}

    /**
     * Sync user to User Service (dispatch job if queue enabled)
     *
     * @param  object|array  $keycloakUser  Data dari Socialite Keycloak
     * @param  object|array  $localUser  User model dari database lokal
     * @param  array  $tokenData  Token data untuk disimpan (optional jika sudah disimpan)
     */
    public function syncUser(object|array $keycloakUser, object|array $localUser, array $tokenData = []): void
    {
        $localUserId = $this->extractUserId($localUser);
        $keycloakSub = $this->extractKeycloakSub($keycloakUser);
        $registrationEnabled = config('user-service-sdk.sync.registration_enabled', true);

        // Store tokens if provided
        if (! empty($tokenData)) {
            $tokenData['keycloak_id'] = $keycloakSub;
            $this->tokenManager->storeTokens($localUserId, $tokenData);
        }

        // Check if sync is enabled
        if (! config('user-service-sdk.sync.enabled', true)) {
            Log::debug('UserServiceSdk: User sync is disabled');

            return;
        }

        // Prepare user data for User Service
        $userData = $this->mapUserData($keycloakUser, $localUser);

        // Dispatch job or sync directly
        if ($registrationEnabled && config('user-service-sdk.sync.queue', 'default')) {
            $this->dispatchSyncJob($localUserId, $keycloakSub, $userData);
        } else {
            $this->syncUserDirectly($localUserId, $keycloakSub, $userData);
        }
    }

    /**
     * Dispatch sync job to queue
     */
    protected function dispatchSyncJob(int|string $localUserId, string $keycloakSub, array $userData): void
    {
        $queue = config('user-service-sdk.sync.queue', 'default');

        SyncUserToUserServiceJob::dispatch($localUserId, $keycloakSub, $userData)
            ->onQueue($queue);

        Log::info('UserServiceSdk: User sync job dispatched', [
            'local_user_id' => $localUserId,
            'keycloak_sub' => $keycloakSub,
            'queue' => $queue,
        ]);
    }

    /**
     * Sync user directly (synchronous)
     */
    public function syncUserDirectly(int|string $localUserId, string $keycloakSub, array $userData): bool
    {
        try {
            Log::info('UserServiceSdk: Starting user sync', [
                'local_user_id' => $localUserId,
                'keycloak_sub' => $keycloakSub,
            ]);

            $result = $this->userServiceClient->createOrUpdateUser(
                $localUserId,
                $keycloakSub,
                $userData
            );

            event(new UserSyncSucceeded($localUserId, $keycloakSub, $result));

            Log::info('UserServiceSdk: User sync completed', [
                'local_user_id' => $localUserId,
                'keycloak_sub' => $keycloakSub,
                'result' => $result,
            ]);

            return true;

        } catch (UserServiceException $e) {
            event(new UserSyncFailed($localUserId, $keycloakSub, $e->getMessage(), $e->getCode()));

            Log::error('UserServiceSdk: User sync failed', [
                'local_user_id' => $localUserId,
                'keycloak_sub' => $keycloakSub,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            event(new UserSyncFailed($localUserId, $keycloakSub, $e->getMessage(), 0));

            Log::error('UserServiceSdk: User sync exception', [
                'local_user_id' => $localUserId,
                'keycloak_sub' => $keycloakSub,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Map Keycloak user data to User Service format
     */
    protected function mapUserData(object|array $keycloakUser, object|array $localUser): array
    {
        $keycloakData = is_object($keycloakUser) ? (array) $keycloakUser : $keycloakUser;
        $localData = is_object($localUser) ? $localUser->toArray() : $localUser;

        // Get raw user attributes from Keycloak
        $rawAttributes = $keycloakData['user'] ?? $keycloakData['attributes'] ?? [];

        $mapping = config('user-service-sdk.field_mapping', []);

        $userData = [
            'id' => $keycloakData['id'] ?? $keycloakData['sub'] ?? null,
        ];

        foreach ($mapping as $userServiceField => $keycloakField) {
            $value = $keycloakData[$keycloakField]
                ?? $rawAttributes[$keycloakField]
                ?? $localData[$keycloakField]
                ?? null;

            if ($value !== null) {
                $userData[$userServiceField] = $value;
            }
        }

        // Ensure required fields
        if (empty($userData['username']) && ! empty($userData['email'])) {
            $userData['username'] = explode('@', $userData['email'])[0];
        }

        return array_filter($userData, fn ($v) => $v !== null);
    }

    /**
     * Extract user ID from local user model
     */
    protected function extractUserId(object|array $localUser): int|string
    {
        if (is_array($localUser)) {
            return $localUser['id'] ?? throw new \InvalidArgumentException('Local user must have an ID');
        }

        return $localUser->id ?? $localUser->getKey() ?? throw new \InvalidArgumentException('Local user must have an ID');
    }

    /**
     * Extract Keycloak Sub from Keycloak user data
     */
    protected function extractKeycloakSub(object|array $keycloakUser): string
    {
        if (is_array($keycloakUser)) {
            return $keycloakUser['id'] ?? $keycloakUser['sub'] ?? throw new \InvalidArgumentException('Keycloak user must have sub/id');
        }

        return $keycloakUser->id ?? $keycloakUser->sub ?? throw new \InvalidArgumentException('Keycloak user must have sub/id');
    }
}
