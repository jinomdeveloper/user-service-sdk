<?php

namespace Jinom\UserServiceSdk;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Jinom\UserServiceSdk\Services\TokenManager;
use Jinom\UserServiceSdk\Services\UserServiceClient;
use Jinom\UserServiceSdk\Services\UserSyncService;

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
        // Register TokenManager as singleton
        $this->app->singleton(TokenManager::class, function ($app) {
            return new TokenManager();
        });

        // Register UserServiceClient as singleton
        $this->app->singleton(UserServiceClient::class, function ($app) {
            return new UserServiceClient(
                $app->make(TokenManager::class)
            );
        });

        // Register UserSyncService as singleton
        $this->app->singleton(UserSyncService::class, function ($app) {
            return new UserSyncService(
                $app->make(TokenManager::class),
                $app->make(UserServiceClient::class)
            );
        });

        // Register main SDK class as singleton
        $this->app->singleton(UserServiceSdk::class, function ($app) {
            return new UserServiceSdk(
                $app->make(TokenManager::class),
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
