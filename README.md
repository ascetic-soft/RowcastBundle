# RowcastBundle

Symfony bundle for integrating:

- `ascetic-soft/rowcast` (Connection + DataMapper)
- `ascetic-soft/rowcast-schema` (optional schema/migration services + console commands)

## Requirements

- PHP `>=8.4`
- Symfony components:
  - `symfony/config`
  - `symfony/dependency-injection`
  - `symfony/http-kernel`
- Optional:
  - `ascetic-soft/rowcast-schema`
  - `symfony/console`
  - `ascetic-soft/rowcast-profiler` (+ `symfony/framework-bundle` for the profiler panel)

## Installation

```bash
composer require ascetic-soft/rowcast-bundle
```

**Monorepo / contributing:** if `ascetic-soft/rowcast-profiler` is not on Packagist yet, add a path repository in `composer.json` (see this repo’s `RowcastBundle/composer.json`) so `^1.0` resolves to the sibling `RowcastProfiler` directory. Remove the path repository after the profiler is published.

If Symfony Flex does not auto-register the bundle, add it manually to `config/bundles.php`:

```php
<?php

return [
    AsceticSoft\RowcastBundle\RowcastBundle::class => ['all' => true],
];
```

## Symfony Flex private recipe

For automatic creation of `config/packages/rowcast.yaml` and `.env` variables, use a private Symfony Flex recipe repository.

Prepared recipe files are located in:

- `../RowcastBundleRecipes/index.json`
- `../RowcastBundleRecipes/ascetic-soft.rowcast-bundle.1.0.json`

Current endpoint configured in `composer.json`:

```json
"extra": {
    "symfony": {
        "bundle": "AsceticSoft\\RowcastBundle\\RowcastBundle",
        "endpoint": [
            "https://api.github.com/repos/ABorodulin/rowcast-bundle-recipes/contents/index.json",
            "flex://defaults"
        ]
    }
}
```

Before using in production, replace `ABorodulin/rowcast-bundle-recipes` with your real GitHub account/repository and ensure the recipe JSON files are published at that endpoint.

## Configuration

Create `config/packages/rowcast.yaml`:

```yaml
rowcast:
  connection:
    dsn: '%env(DATABASE_DSN)%'
    username: '%env(DATABASE_USER)%'
    password: '%env(DATABASE_PASSWORD)%'
    options: []
    nest_transactions: true

  schema:
    path: '%kernel.project_dir%/database/schema.php'
    migrations_path: '%kernel.project_dir%/database/migrations'
    migration_table: '_rowcast_migrations'
    ignore_tables: []

  profiler:
    enabled: false
    collect_params: true
    slow_query_threshold_ms: 50
    max_queries: 500
```

### SQL profiler (optional)

Install the profiler package and enable it in config (e.g. only in `dev`):

```bash
composer require ascetic-soft/rowcast-profiler
# For the web debug toolbar / profiler UI:
composer require symfony/web-profiler-bundle --dev
```

```yaml
# config/packages/dev/rowcast.yaml
rowcast:
    profiler:
        enabled: true
```

When `profiler.enabled` is `true` and the package is present, the bundle decorates `AsceticSoft\Rowcast\Connection` with `ConnectionProfiler`, resets the query store between requests (`kernel.reset`), and registers `RowcastDataCollector` if `symfony/framework-bundle` is installed (toolbar + profiler panel **Rowcast**).

### Attributes schema support

`rowcast.schema.path` can point either to:

- a schema file (`.php`, `.yaml`, `.yml`)
- a directory with classes that contain Rowcast attributes

Parser selection is automatic:

- if `path` is a directory and `AttributeSchemaParser` is available, bundle uses attribute parser
- otherwise bundle uses file parser based on file extension

Example for attribute-based schema:

```yaml
rowcast:
  schema:
    path: '%kernel.project_dir%/src/Entity'
    migrations_path: '%kernel.project_dir%/database/migrations'
    migration_table: '_rowcast_migrations'
    ignore_tables: []
```

## Registered services

Core services (always):

- `AsceticSoft\Rowcast\Connection`
- alias `AsceticSoft\Rowcast\ConnectionInterface` -> `AsceticSoft\Rowcast\Connection`
- `AsceticSoft\Rowcast\DataMapper`
- `rowcast.pdo` (factory: `Connection::getPdo()`)

Profiler services (only when `profiler.enabled` is true and `ascetic-soft/rowcast-profiler` is installed):

- `AsceticSoft\RowcastProfiler\InMemoryQueryProfileStore` (tag `kernel.reset`)
- `AsceticSoft\RowcastProfiler\DefaultParameterSanitizer`
- `AsceticSoft\RowcastProfiler\SqlClassifier`
- `AsceticSoft\RowcastProfiler\RowcastProfiler`
- `AsceticSoft\RowcastProfiler\ConnectionProfiler` (decorates `AsceticSoft\Rowcast\Connection`)
- `AsceticSoft\RowcastBundle\DataCollector\RowcastDataCollector` (when `symfony/framework-bundle` is available)

Schema services (only when `ascetic-soft/rowcast-schema` is installed):

- `AsceticSoft\RowcastSchema\Parser\SchemaParserInterface`
- `AsceticSoft\RowcastSchema\Diff\SchemaDiffer`
- `AsceticSoft\RowcastSchema\Migration\MigrationGenerator`
- `AsceticSoft\RowcastSchema\Migration\MigrationLoader`
- `AsceticSoft\RowcastSchema\Introspector\IntrospectorFactory`
- `AsceticSoft\RowcastSchema\Platform\PlatformFactory`
- `AsceticSoft\RowcastSchema\Platform\PlatformInterface`
- `AsceticSoft\RowcastSchema\Cli\TableIgnoreMatcher`
- `AsceticSoft\RowcastSchema\Migration\DatabaseMigrationRepository`
- alias `AsceticSoft\RowcastSchema\Migration\MigrationRepositoryInterface` -> `DatabaseMigrationRepository`
- `AsceticSoft\RowcastSchema\Migration\MigrationRunner`

## Console commands

When both `ascetic-soft/rowcast-schema` and `symfony/console` are available:

- `bin/console rowcast:diff [--dry-run]`
- `bin/console rowcast:make`
- `bin/console rowcast:migrate`
- `bin/console rowcast:rollback [--step=1]`
- `bin/console rowcast:status`

## Usage example

```php
<?php

use AsceticSoft\Rowcast\DataMapper;

final readonly class UserRepository
{
    public function __construct(private DataMapper $mapper)
    {
    }

    // ...
}
```

