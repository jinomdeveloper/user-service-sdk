<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Jinom\UserServiceSdk\Exceptions\UserServiceException;
use Jinom\UserServiceSdk\Services\TokenManager;
use Jinom\UserServiceSdk\Services\UserServiceClient;

class FakeTokenManager extends TokenManager
{
    public function __construct(private ?string $token = 'fake-token') {}

    public function getValidToken(int|string $userId): ?string
    {
        return $this->token;
    }
}

it('rejects registration when disabled and user not found', function () {
    Config::set('user-service-sdk.user_service.base_url', 'http://user-service.test');
    Config::set('user-service-sdk.sync.registration_enabled', false);

    Http::fake([
        'http://user-service.test/api/v1/users-management/keycloak/*' => Http::response([], 404),
        'http://user-service.test/api/v1/users/register' => Http::response(['created' => true], 201),
    ]);

    $client = new UserServiceClient(new FakeTokenManager('token'));

    expect(fn () => $client->createOrUpdateUser(1, 'keycloak-sub', ['id' => 'keycloak-sub']))
        ->toThrow(UserServiceException::class, 'User not found: keycloak-sub');

    Http::assertSentCount(1);
});
