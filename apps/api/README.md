# STORVIA Starter API

Laravel REST API for STORVIA Starter Kit.

## Requirements

- PHP 8.3+ (release verification targets PHP 8.4)
- Composer 2
- MariaDB

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure MariaDB and first-party SPA origins, then follow the repository-level installation guide in `../../docs/INSTALLATION.md`.

`DatabaseSeeder` seeds only the safe access-control catalog unless the optional local demo flag is explicitly enabled. It does not ship an operational password.

## Verification

```bash
vendor/bin/pint --test
php artisan test
```

Never commit `.env`, database exports, logs, runtime caches, or objects under private storage.
