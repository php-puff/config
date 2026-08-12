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
use Puff\Config\Environment;

final class EnvironmentTest extends TestCase
{
    /** @var list<string> */
    private array $keys = [];
    /** @var list<string> */
    private array $files = [];
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->keys as $key) {
            \putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
        foreach ($this->files as $file) {
            if (\is_file($file)) {
                \unlink($file);
            }
        }
        foreach (\array_reverse($this->directories) as $directory) {
            if (\is_dir($directory)) {
                \rmdir($directory);
            }
        }
    }

    public function testLoadsCommonDotenvSyntaxAndPopulatesEnvironment(): void
    {
        $file = $this->envFile(<<<'ENV'
# comment
PUFF_TEST_NAME="PHP Unison Fiber Framework"
PUFF_TEST_EMPTY=
PUFF_TEST_SINGLE='literal ${PUFF_TEST_NAME}'
PUFF_TEST_EXPANDED=${PUFF_TEST_NAME}/app
export PUFF_TEST_WORKERS=4 # workers
ENV);

        $environment = Environment::load($file);

        self::assertFalse(\getenv('PUFF_TEST_NAME'));
        $environment = $environment->apply();

        self::assertSame('PHP Unison Fiber Framework', $environment->get('PUFF_TEST_NAME'));
        self::assertSame('', $environment->get('PUFF_TEST_EMPTY'));
        self::assertSame('literal ${PUFF_TEST_NAME}', $environment->get('PUFF_TEST_SINGLE'));
        self::assertSame('PHP Unison Fiber Framework/app', $environment->get('PUFF_TEST_EXPANDED'));
        self::assertSame('4', $_ENV['PUFF_TEST_WORKERS']);
        self::assertSame('4', $_SERVER['PUFF_TEST_WORKERS']);
    }

    public function testExistingProcessEnvironmentHasPriority(): void
    {
        $this->track('PUFF_TEST_PRIORITY');
        \putenv('PUFF_TEST_PRIORITY=system');
        $_ENV['PUFF_TEST_PRIORITY'] = 'system';
        $_SERVER['PUFF_TEST_PRIORITY'] = 'system';
        $file = $this->envFile('PUFF_TEST_PRIORITY=file');

        self::assertSame('system', Environment::load($file)->apply()->get('PUFF_TEST_PRIORITY'));
        self::assertSame('system', \getenv('PUFF_TEST_PRIORITY'));
    }

    public function testEnvironmentOverridesExistingConfigUsingDoubleUnderscores(): void
    {
        $root = $this->temporaryDirectory();
        \file_put_contents($root . '/.env', "APP__LOG=2\nAPP__DEBUG=true\nUNKNOWN__VALUE=ignored\n");
        \file_put_contents($root . '/config.php', '<?php return ["app" => ["log" => 1, "debug" => false]];');
        $this->keys = [...$this->keys, 'APP__LOG', 'APP__DEBUG', 'UNKNOWN__VALUE'];
        $this->files[] = $root . '/config.php';

        $config = Config::load($root, $root . '/config.php');

        self::assertSame(2, $config->get('app.log'));
        self::assertTrue($config->get('app.debug'));
        self::assertFalse($config->has('unknown.value'));
    }

    public function testLoadAcceptsAFileAndAppliesEnvironmentInternally(): void
    {
        $root = $this->temporaryDirectory();
        \file_put_contents($root . '/.env', 'APP__LOG=7');
        \file_put_contents($root . '/config.php', '<?php return ["app" => ["log" => 1]];');
        $this->keys[] = 'APP__LOG';
        $this->files[] = $root . '/config.php';

        $config = $this->loadFromRoot($root, $root . '/config.php');

        self::assertSame(7, $config->get('app.log'));
    }

    public function testLoadReadsOnlyRootEnvironmentAndDoesNotLoadNamedEnvironmentConfig(): void
    {
        $root = $this->temporaryDirectory();
        \mkdir($root . '/config');
        $this->directories[] = $root . '/config';
        \file_put_contents($root . '/.env', "APP__LOG=8\n");
        \file_put_contents($root . '/config/config.php', '<?php return ["app" => ["log" => 1, "name" => "base"]];');
        \file_put_contents($root . '/config/test.php', '<?php return ["app" => ["name" => "test"]];');
        $this->keys[] = 'APP__LOG';

        $config = $this->loadFromRoot($root, $root . '/config');

        self::assertSame(8, $config->get('app.log'));
        self::assertSame('base', $config->get('app.name'));
    }

    public function testInvalidEntryThrowsUsefulException(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('line 1');
        Environment::load($this->envFile('INVALID ENTRY'));
    }

    private function envFile(string $content): string
    {
        $file = $this->temporaryFile($content);
        $this->keys = [...$this->keys, ...\array_keys((new \Puff\Config\EnvParser())->parse($content))];
        $this->addToAssertionCount(0);
        return $file;
    }

    private function temporaryFile(string $content): string
    {
        $file = \tempnam(\sys_get_temp_dir(), 'puff-env-');
        if ($file === false) {
            self::fail('Unable to create temporary file.');
        }
        \file_put_contents($file, $content);
        $this->files[] = $file;
        return $file;
    }

    private function temporaryDirectory(): string
    {
        $directory = \sys_get_temp_dir() . '/puff-config-' . \bin2hex(\random_bytes(6));
        if (!\mkdir($directory)) {
            self::fail('Unable to create temporary directory.');
        }
        $this->directories[] = $directory;
        $this->files[] = $directory . '/.env';
        $this->files[] = $directory . '/config/config.php';
        $this->files[] = $directory . '/config/test.php';
        return $directory;
    }

    private function track(string $key): void
    {
        if (!\in_array($key, $this->keys, true)) {
            $this->keys[] = $key;
        }
    }

    private function loadFromRoot(string $root, string ...$paths): Config
    {
        $workingDirectory = \getcwd();
        if (!\chdir($root)) {
            self::fail("Unable to enter temporary root [{$root}].");
        }
        try {
            return Config::load($root, ...$paths);
        } finally {
            if ($workingDirectory !== false) {
                \chdir($workingDirectory);
            }
        }
    }
}
