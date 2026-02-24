<?php

namespace Jinom\UserServiceSdk;

use Jinom\Keycloak\Contracts\TokenManagerInterface;
use Jinom\UserServiceSdk\Services\UserServiceClient;
use Jinom\UserServiceSdk\Services\UserSyncService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class UserServiceSdkServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('user-service-sdk')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        // Register UserServiceClient as singleton (uses TokenManager from keycloak-sdk)
        $this->app->singleton(UserServiceClient::class, function ($app) {
            return new UserServiceClient(
                $app->make(TokenManagerInterface::class)
            );
        });

        // Register UserSyncService as singleton
        $this->app->singleton(UserSyncService::class, function ($app) {
            return new UserSyncService(
                $app->make(TokenManagerInterface::class),
                $app->make(UserServiceClient::class)
            );
        });

        // Register main SDK class as singleton
        $this->app->singleton(UserServiceSdk::class, function ($app) {
            return new UserServiceSdk(
                $app->make(TokenManagerInterface::class),
                $app->make(UserServiceClient::class),
                $app->make(UserSyncService::class)
            );
        });

        // Alias for facade
        $this->app->alias(UserServiceSdk::class, 'user-service-sdk');
    }

    public function packageBooted(): void
    {
        // Additional boot logic if needed
    }
}
