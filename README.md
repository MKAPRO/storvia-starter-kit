# STORVIA Starter Kit

STORVIA Starter Kit is a self-hosted file-management foundation built with Laravel and Next.js. It provides a secure core workspace for personal and department storage, administration, quotas, file policies, privacy controls, and bilingual RTL/LTR operation.

## Highlights

- Protected initial setup and first-Super-Admin bootstrap
- Authentication, session freshness, active-user revocation, and locale support
- Users, departments, roles, permissions, and organizational scope
- Personal and department FileSpaces
- File/folder browse, create, rename, move, upload, and download
- Favorites and Trash / restore / empty-trash
- Storage quotas and upload policy
- File type registry and department policies
- Direct resource privacy/password/access-policy controls
- Basic user and administration dashboards
- Internal append-only audit recording
- Arabic/English, RTL/LTR, theme, and responsive workspace UI
- Security headers, CORS/Sanctum, and trusted-proxy hardening
- Optional local demo dataset with no committed password

## Starter boundaries

This Starter Kit intentionally does **not** include collaboration/distribution/publication modules such as Internal Sharing / Shared With Me, public share links, company-folder distribution, advanced dashboard analytics, or the administration audit-log browser.

The internal audit recorder remains part of the core because file and administration mutations use it for security and traceability.

## Requirements

- PHP 8.3+ (release verification targets PHP 8.4)
- Composer 2
- MariaDB
- Node.js 24 and npm

## Repository layout

```text
apps/
  api/   Laravel REST API
  web/   Next.js frontend
docs/    Installation, architecture, demo, and repository guidance
```

## Quick start

### API

```bash
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
```

Configure MariaDB plus the SPA/CORS origins in `.env`, then run migrations in maintenance mode and seed the safe access-control catalog:

```bash
php artisan down
php artisan migrate --force
php artisan db:seed --force
php artisan up
php artisan storvia:make-super-admin
```

### Web

```bash
cd apps/web
npm ci
cp .env.example .env.local
npm run dev
```

Set `NEXT_PUBLIC_STORVIA_API_URL` in `.env.local` to the Laravel API origin.

For complete installation guidance, see [`docs/INSTALLATION.md`](docs/INSTALLATION.md).

## Optional demo dataset

The normal seed path creates no operational user. A local-only demo dataset can be enabled explicitly with a password supplied through the environment. See [`docs/DEMO.md`](docs/DEMO.md).

## Security

Never commit operational `.env` files, credentials, database exports, uploaded user files, runtime logs/caches, or private keys. See [`SECURITY.md`](SECURITY.md) and [`docs/REPOSITORY-HYGIENE.md`](docs/REPOSITORY-HYGIENE.md).

## Documentation

- [`docs/INSTALLATION.md`](docs/INSTALLATION.md)
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
- [`docs/DEMO.md`](docs/DEMO.md)
- [`docs/REPOSITORY-HYGIENE.md`](docs/REPOSITORY-HYGIENE.md)

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md).

## License

STORVIA Starter Kit is released under the [MIT License](LICENSE).

---

© 2026 MKAPRO.
Powered by AnsiTeam™.
