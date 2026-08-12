<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/config
 * https://github.com/php-puff/config/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Config\Tests;

use PHPUnit\Framework\TestCase;
use Puff\Config\Config;
use Puff\Di\Container;

final class FunctionsTest extends TestCase
{
    protected function tearDown(): void
    {
        \putenv('PUFF_TEST_NULL');
        Container::setInstance(null);
    }

    public function testConfigHelperUsesRegisteredConfigInstance(): void
    {
        $container = new Container();
        $repository = new Config(['app' => ['name' => 'Puff']]);
        $container->instance(Config::class, $repository);
        Container::setInstance($container);

        self::assertSame($repository, \config());
        self::assertSame('Puff', \config('app.name'));
        self::assertSame($repository, \config(['app.version' => '1.0.0']));
        self::assertSame('1.0.0', \config('app.version'));
    }

    public function testEnvHelperUsesConfigValueConversion(): void
    {
        \putenv('PUFF_TEST_NULL=null');

        self::assertNull(\env('PUFF_TEST_NULL'));
    }
}
