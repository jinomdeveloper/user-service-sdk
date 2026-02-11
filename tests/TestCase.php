<?php

namespace Jinom\UserServiceSdk\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Jinom\UserServiceSdk\UserServiceSdkServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            UserServiceSdkServiceProvider::class,
        ];
    }
}
