<?php

namespace Jinom\UserServiceSdk\Tests\Helpers;

use Jinom\UserServiceSdk\Services\TokenManager;

class FakeTokenManager extends TokenManager
{
    public function __construct(private ?string $token = 'fake-token') {}

    public function getValidToken(int|string $userId): ?string
    {
        return $this->token;
    }
}
