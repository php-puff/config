# Puff Config

独立的点号路径配置仓库，支持数组、配置文件、`.env` 和系统环境变量。

```php
use Puff\Config\Config;

$basePath = __DIR__;
$config = Config::load($basePath, $basePath . '/config', $basePath . '/app');
```

`load()` 固定加载 `$basePath/.env`。目录只自动加载 `config.php`，不会根据 `ENV` 加载 `dev.php`、`test.php` 等额外环境配置文件。不存在的路径和未返回数组的配置文件会被跳过。

系统环境变量优先于 `.env`。双下划线表示配置层级，且默认只覆盖已经存在的配置键：

```dotenv
APP__LOG=2
DATABASE__CONNECTIONS__MYSQL__HOST=127.0.0.1
```

对应 `app.log` 和 `database.connections.mysql.host`。覆盖值会根据原配置值保留 `bool`、`int` 或 `float` 类型。

普通环境变量可以通过 `env()` 读取：

```php
$port = env('PUFF_PORT', 8620);
```

`Environment::load()` 只解析文件。需要写入进程环境时显式调用 `apply()`：

```php
use Puff\Config\Environment;

$environment = Environment::load($basePath . '/.env');
$effective = $environment->apply();
```
