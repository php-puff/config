<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/config
 * https://github.com/php-puff/config/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Config;

use Composer\InstalledVersions;

final class ConfigPublisher
{
    public static function publish(mixed $event = null): int
    {
        $basePath = \is_string($event) ? $event : (\getcwd() ?: '.');
        $published = 0;
        foreach (InstalledVersions::getInstalledPackages() as $package) {
            $packagePath = InstalledVersions::getInstallPath($package);
            if ($packagePath === null) {
                continue;
            }

            $manifest = $packagePath . '/composer.json';
            if (!\is_file($manifest)) {
                continue;
            }

            $metadata = \json_decode((string) \file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
            $configurations = $metadata['extra']['puff']['config'] ?? null;
            if (!\is_array($configurations)) {
                continue;
            }

            foreach ($configurations as $target => $source) {
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

}
