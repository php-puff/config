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

    public function testServiceProviderLoadsFromComposerRootPath(): void
    {
        $container = new Container();

        (new ServiceProvider($container))->register();

        self::assertInstanceOf(Config::class, $container->make(Config::class));
        self::assertSame($container->make(Config::class), $container->make('config'));
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
