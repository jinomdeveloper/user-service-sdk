<?php

namespace Jinom\UserServiceSdk\Tests;

use Jinom\UserServiceSdk\UserServiceSdkServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            UserServiceSdkServiceProvider::class,
        ];
    }
}
