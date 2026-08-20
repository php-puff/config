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
use Puff\Config\ConfigException;
use Puff\Config\ConfigPublisher;
use Puff\Config\ServiceProvider;
use Puff\Di\Container;

final class ConfigTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach (\array_reverse($this->files) as $path) {
            if (\is_file($path)) {
                \unlink($path);
            } elseif (\is_dir($path)) {
                \rmdir($path);
            }
        }
    }

    public function testStartsEmptyAndSupportsDotPathsAndBatchValues(): void
    {
        $config = new Config();

        self::assertSame([], $config->all());
        self::assertSame($config, $config->set('site.name', 'Puff'));
        self::assertSame($config, $config->set(['site.enabled' => true, 'nullable' => null]));
        self::assertSame('Puff', $config->get('site.name'));
        self::assertSame(['site.name' => 'Puff', 'missing' => 'fallback'], $config->get(['site.name', 'missing'], 'fallback'));
        self::assertTrue($config->has('nullable'));
        self::assertNull($config->get('nullable'));
    }

    public function testRejectsScalarPathConflictsWithoutChangingConfiguration(): void
    {
        $config = new Config(['site' => ['name' => 'Puff']]);

        try {
            $config->set('site.name.first', 'P');
            self::fail('Expected a configuration path conflict.');
        } catch (ConfigException $exception) {
            self::assertStringContainsString('site.name.first', $exception->getMessage());
        }
        self::assertSame(['site' => ['name' => 'Puff']], $config->all());
    }

    public function testLoadsFilesInOrderAndSkipsMissingAndInvalidFiles(): void
    {
        $root = $this->directory();
        $configDirectory = $this->directory($root . '/config');
        $first = $this->file($configDirectory . '/config.php', '<?php return ["site" => ["name" => "Puff", "ports" => [1, 2]]];');
        $second = $this->file($root . '/override.php', '<?php return ["site" => ["name" => "Override", "ports" => [3]]];');
        $invalid = $this->file($root . '/invalid.php', '<?php return "invalid";');

        $config = Config::load($root, $configDirectory, $root . '/missing.php', $invalid, $first, $second);

        self::assertSame('Override', $config->get('site.name'));
        self::assertSame([3, 2], $config->get('site.ports'));
    }

    public function testLoadsDirectoryFilesByTheirRelativeNames(): void
    {
        $root = $this->directory();
        $directory = $this->directory($root . '/config');
        $this->file($directory . '/config.php', '<?php return ["timezone" => "UTC"];');
        $this->file($directory . '/cache.php', '<?php return ["default" => "memory"];');
        $this->file($directory . '/http.php', '<?php return ["addr" => "127.0.0.1:8620"];');

        $config = Config::load($root, $directory);

        self::assertSame('UTC', $config->get('timezone'));
        self::assertSame('memory', $config->get('cache.default'));
        self::assertSame('127.0.0.1:8620', $config->get('http.addr'));
    }

    public function testServiceProviderLoadsFromComposerRootPath(): void
    {
        $container = new Container();

        (new ServiceProvider($container))->register();

        self::assertInstanceOf(Config::class, $container->make(Config::class));
        self::assertSame($container->make(Config::class), $container->make('config'));
    }

    public function testPublishesPackageConfigurationWithoutOverwritingApplicationFiles(): void
    {
        $root = $this->directory();
        $vendor = $this->directory($root . '/vendor');
        $composer = $this->directory($vendor . '/composer');
        $package = $this->directory($vendor . '/puff-cache');
        $packageConfig = $this->directory($package . '/config');
        $this->file($packageConfig . '/cache.php', '<?php return ["default" => "memory"];');
        $installed = [[
            'name' => 'puff/cache',
            'install_path' => '../puff-cache',
            'extra' => ['puff' => ['config' => ['cache.php' => 'config/cache.php']]],
        ]];
        $this->file($composer . '/installed.json', (string) \json_encode($installed, JSON_THROW_ON_ERROR));

        self::assertSame(1, ConfigPublisher::publish($root));
        self::assertSame(0, ConfigPublisher::publish($root));
        self::assertSame('<?php return ["default" => "memory"];', \file_get_contents($root . '/config/cache.php'));

        $this->files[] = $root . '/config';
        $this->files[] = $root . '/config/cache.php';
    }

    public function testPublishesPackageNamedConfigurationFile(): void
    {
        $root = $this->directory();
        $vendor = $this->directory($root . '/vendor');
        $composer = $this->directory($vendor . '/composer');
        $package = $this->directory($vendor . '/http-client');
        $packageConfig = $this->directory($package . '/config');
        $this->file($packageConfig . '/http.client.php', '<?php return [];');
        $installed = [[
            'name' => 'puff/http-client',
            'install_path' => '../http-client',
            'extra' => ['puff' => ['config' => ['http.client.php' => 'config/http.client.php']]],
        ]];
        $this->file($composer . '/installed.json', (string) \json_encode($installed, JSON_THROW_ON_ERROR));

        self::assertSame(1, ConfigPublisher::publish($root));
        self::assertFileExists($root . '/config/http.client.php');

        $this->files[] = $root . '/config';
        $this->files[] = $root . '/config/http.client.php';
    }

    private function directory(?string $path = null): string
    {
        $path ??= \sys_get_temp_dir() . '/puff-config-' . \bin2hex(\random_bytes(6));
        if (!\mkdir($path)) {
            self::fail("Unable to create temporary directory [{$path}].");
        }
        $this->files[] = $path;
        return $path;
    }

    private function file(string $path, string $content): string
    {
        if (\file_put_contents($path, $content) === false) {
            self::fail("Unable to create temporary file [{$path}].");
        }
        $this->files[] = $path;
        return $path;
    }
}
