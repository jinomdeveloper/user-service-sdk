<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Jinom\UserServiceSdk\Exceptions\UserServiceException;
use Jinom\UserServiceSdk\Services\UserServiceClient;
use Jinom\UserServiceSdk\Tests\Helpers\FakeTokenManager;
use Jinom\UserServiceSdk\Tests\Helpers\TestConstants;

it('rejects registration when disabled and user not found', function () {
    Config::set('user-service-sdk.user_service.base_url', 'http://user-service.test');
    Config::set('user-service-sdk.sync.registration_enabled', false);

    Http::fake([
        'http://user-service.test/api/v1/users-management/keycloak/*' => Http::response([], 404),
    ]);

    $client = new UserServiceClient(new FakeTokenManager('token'));

    expect(fn () => $client->createOrUpdateUser(1, 'keycloak-sub', ['id' => 'keycloak-sub']))
        ->toThrow(UserServiceException::class, 'User registration is disabled: keycloak-sub');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), TestConstants::REGISTRATION_PATH));
});

it('updates existing user when registration is disabled', function () {
    Config::set('user-service-sdk.user_service.base_url', 'http://user-service.test');
    Config::set('user-service-sdk.sync.registration_enabled', false);

    Http::fake([
        'http://user-service.test/api/v1/users-management/keycloak/*' => Http::response(['id' => 'existing-id'], 200),
        'http://user-service.test/api/v1/users-management/existing-id' => Http::response(['updated' => true], 200),
    ]);

    $client = new UserServiceClient(new FakeTokenManager('token'));

    $result = $client->createOrUpdateUser(1, 'keycloak-sub', ['email' => 'user@example.com']);

    expect($result)->toMatchArray(['updated' => true]);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), TestConstants::REGISTRATION_PATH));
});
