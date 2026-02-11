<?php

namespace Jinom\UserServiceSdk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserSyncFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int|string $localUserId,
        public string $keycloakSub,
        public string $errorMessage,
        public int $errorCode = 0
    ) {}
}
