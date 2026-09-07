<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($app->environment('testing') && ($connection !== 'sqlite' || $database !== ':memory:')) {
            throw new RuntimeException(
                "Tests must use the isolated SQLite in-memory database. Active connection: {$connection}; database: {$database}."
            );
        }

        return $app;
    }
}
