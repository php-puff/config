<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/config
 * https://github.com/php-puff/config/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Config;

final class ConfigPublisher
{
    public static function publish(mixed $event = null): int
    {
        $basePath = \is_string($event) ? $event : (\getcwd() ?: '.');
        $composerDirectory = $basePath . '/vendor/composer';
        $file = $composerDirectory . '/installed.json';
        if (!\is_file($file)) {
            return 0;
        }
        $decoded = \json_decode((string) \file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $packages = \is_array($decoded['packages'] ?? null) ? $decoded['packages'] : $decoded;
        if (!\is_array($packages)) {
            return 0;
        }

        $published = 0;
        foreach ($packages as $package) {
            if (!\is_array($package) || !\is_array($package['extra']['puff']['config'] ?? null)) {
                continue;
            }
            $installPath = $package['install_path'] ?? null;
            if (!\is_string($installPath) || $installPath === '') {
                continue;
            }
            $packagePath = self::absolutePath($composerDirectory, $installPath);
            foreach ($package['extra']['puff']['config'] as $target => $source) {
                if (\is_string($target) && \is_string($source)) {
                    $published += self::publishFile($basePath, $packagePath, $target, $source) ? 1 : 0;
                }
            }
        }
        return $published;
    }

    private static function publishFile(string $basePath, string $packagePath, string $target, string $source): bool
    {
        if (\preg_match('#^(?:[a-z][a-z0-9_-]*/)*[a-z][a-z0-9_.-]*\.php$#D', $target) !== 1
            || \preg_match('#^(?!/)(?!.*\.\.)[^\0]+\.php$#D', $source) !== 1) {
            throw new ConfigException("Invalid published configuration path [{$target}].");
        }
        $sourceFile = \realpath($packagePath . '/' . $source);
        $packageRoot = \realpath($packagePath);
        if ($sourceFile === false || $packageRoot === false
            || !\str_starts_with($sourceFile, $packageRoot . DIRECTORY_SEPARATOR)) {
            throw new ConfigException("Configuration source [{$source}] does not exist in [{$packagePath}].");
        }
        $targetFile = \rtrim($basePath, DIRECTORY_SEPARATOR) . '/config/' . $target;
        if (\is_file($targetFile)) {
            return false;
        }
        $directory = \dirname($targetFile);
        if (!\is_dir($directory) && !\mkdir($directory, 0755, true) && !\is_dir($directory)) {
            throw new ConfigException("Unable to create configuration directory [{$directory}].");
        }
        if (!\copy($sourceFile, $targetFile)) {
            throw new ConfigException("Unable to publish configuration file [{$targetFile}].");
        }
        return true;
    }

    private static function absolutePath(string $directory, string $path): string
    {
        return \str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : $directory . '/' . $path;
    }
}
