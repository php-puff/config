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
use Puff\Di\ServiceProvider as BaseServiceProvider;

final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        if ($this->app->bound(Config::class)) {
            $this->app->alias(Config::class, 'config');
            return;
        }

        $package = InstalledVersions::getRootPackage();
        $root = \realpath($package['install_path']) ?: $package['install_path'];
        $config = Config::load($root, $root . '/config', $root . '/app/config.php');
        $this->app->instance(Config::class, $config);
        $this->app->alias(Config::class, 'config');
    }
}
