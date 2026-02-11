<?php

namespace Jinom\UserServiceSdk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Jinom\UserServiceSdk\Events\UserSyncFailed;
use Jinom\UserServiceSdk\Events\UserSyncSucceeded;
use Jinom\UserServiceSdk\Exceptions\UserServiceException;
use Jinom\UserServiceSdk\Services\UserServiceClient;

class SyncUserToUserServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries;

    /**
     * Number of seconds to wait before retrying.
     */
    public int $backoff;

    public function __construct(
        public int|string $localUserId,
        public string $keycloakSub,
        public array $userData
    ) {
        $this->tries = config('user-service-sdk.sync.retry_times', 3);
        $this->backoff = config('user-service-sdk.sync.retry_delay', 60);
    }

    /**
     * Execute the job.
     */
    public function handle(UserServiceClient $userServiceClient): void
    {
        Log::info('UserServiceSdk Job: Starting user sync', [
            'local_user_id' => $this->localUserId,
            'keycloak_sub' => $this->keycloakSub,
            'attempt' => $this->attempts(),
        ]);

        try {
            $result = $userServiceClient->createOrUpdateUser(
                $this->localUserId,
                $this->keycloakSub,
                $this->userData
            );

            event(new UserSyncSucceeded($this->localUserId, $this->keycloakSub, $result));

            Log::info('UserServiceSdk Job: User sync completed', [
                'local_user_id' => $this->localUserId,
                'keycloak_sub' => $this->keycloakSub,
            ]);

        } catch (UserServiceException $e) {
            Log::error('UserServiceSdk Job: User sync failed', [
                'local_user_id' => $this->localUserId,
                'keycloak_sub' => $this->keycloakSub,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        event(new UserSyncFailed(
            $this->localUserId,
            $this->keycloakSub,
            $exception?->getMessage() ?? 'Unknown error',
            $exception?->getCode() ?? 0
        ));

        Log::error('UserServiceSdk Job: User sync permanently failed', [
            'local_user_id' => $this->localUserId,
            'keycloak_sub' => $this->keycloakSub,
            'error' => $exception?->getMessage(),
        ]);
    }
}
