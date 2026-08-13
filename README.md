# Puff Config

A lightweight dot-notation configuration repository for Puff, with support for PHP configuration files, `.env` files, and process environment variables.

## Requirements

- PHP 8.2 or later
- Composer 2

## Installation

```bash
composer require puff/config
```

The package exposes `Puff\Config\ServiceProvider` through Puff's Composer auto-discovery mechanism. The provider locates the Composer root package and loads:

```text
<project-root>/config/config.php
<project-root>/app/config.php
<project-root>/.env
```

Missing paths and configuration files that do not return an array are ignored.

## Loading Configuration

Configuration can also be loaded directly:

```php
use Puff\Config\Config;

$basePath = __DIR__;
$config = Config::load(
    $basePath,
    $basePath . '/config',
    $basePath . '/app',
);
```

`Config::load()` accepts files or directories. When a directory is provided, only its `config.php` file is loaded. Files are merged in the order supplied using `array_replace_recursive()`, so later values replace earlier values. Numeric arrays therefore use recursive index replacement rather than list concatenation.

The loader always reads `<base-path>/.env`. It does not automatically load environment-specific files such as `dev.php`, `test.php`, or `.env.local`.

## Reading and Writing Values

Use dot notation to access nested configuration:

```php
use Puff\Config\Config;

$config = new Config([
    'site' => [
        'name' => 'Puff',
        'locale' => 'en',
    ],
]);

$name = $config->get('site.name');
$fallback = $config->get('site.locale', 'en');
$exists = $config->has('site.name');

$config->set('site.enabled', true);
$config->set([
    'site.locale' => 'en',
    'server.http.workers' => 4,
]);

$values = $config->all();
```

Writing through a scalar intermediate value is rejected instead of silently replacing existing configuration:

```php
$config = new Config(['site' => ['name' => 'Puff']]);
$config->set('site.name.short', 'P'); // Throws ConfigException
```

## Helpers and Facade

The `config()` helper reads from the repository registered in the Puff container:

```php
$name = config('site.name');
$locale = config('site.locale', 'en');

config([
    'site.enabled' => true,
]);

$repository = config();
```

The global `Config` facade provides the same repository operations:

```php
$name = Config::get('site.name');
Config::set('site.enabled', true);
```

## Environment Overrides

Process environment variables take precedence over values from `.env`. Top-level keys use the same environment variable name; use a double underscore to represent each nested configuration level:

```dotenv
TIMEZONE=UTC
SERVER__HTTP__ADDR=127.0.0.1:8620
DATABASE__CONNECTIONS__MYSQL__HOST=127.0.0.1
```

These variables map to:

```text
timezone
server.http.addr
database.connections.mysql.host
```

Environment overrides are applied only when the corresponding configuration key already exists. Unknown keys are ignored. Keys are converted to lowercase, while a single underscore remains part of the configuration segment.

Values preserve the type of the existing configuration value when it is a boolean, integer, or float:

```php
// Existing configuration
[
    'enabled' => false,
    'workers' => 1,
]

// Environment
ENABLED=true
WORKERS=4
```

The resulting values are `true` and `4`, not strings.

Use `env()` to read an ordinary process environment variable:

```php
$port = env('PUFF_PORT', 8620);
$debug = env('PUFF_DEBUG', false);
```

The default value determines boolean, integer, and float conversion. The string `null` is converted to `null` when the default value is `null`.

## Working with `.env` Files

`Environment::load()` parses a file without changing the current process environment:

```php
use Puff\Config\Environment;

$environment = Environment::load(__DIR__ . '/.env');
$name = $environment->get('APP_NAME');
```

Call `apply()` explicitly to write the parsed values through `putenv()`, `$_ENV`, and `$_SERVER`:

```php
$effective = $environment->apply();
```

`apply()` is immutable by default: an existing process environment variable is not overwritten by a value from the file. Pass `false` to allow replacement:

```php
$effective = $environment->apply(immutable: false);
```

The returned `Environment` contains the effective process environment after the operation.

Supported `.env` syntax includes comments, `export`, empty values, single- and double-quoted values, inline comments, and `${VARIABLE}` expansion:

```dotenv
APP_NAME="Puff Framework"
APP_PATH=${PROJECT_ROOT}/app
APP_LITERAL='${NOT_EXPANDED}'
export WORKERS=4 # inline comment
```

Invalid entries and unreadable environment files throw `Puff\Config\ConfigException`.

## License

Puff Config is open-source software licensed under the MIT license.
