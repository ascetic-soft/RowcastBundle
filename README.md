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

## Installation

```bash
composer require ascetic-soft/rowcast-bundle
```

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
```

## Registered services

Core services (always):

- `AsceticSoft\Rowcast\Connection`
- alias `AsceticSoft\Rowcast\ConnectionInterface` -> `AsceticSoft\Rowcast\Connection`
- `AsceticSoft\Rowcast\DataMapper`
- `rowcast.pdo` (factory: `Connection::getPdo()`)

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

