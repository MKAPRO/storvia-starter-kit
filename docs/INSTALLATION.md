# STORVIA Starter Kit — Fresh Installation

## 1. Prepare the API

1. Install Composer dependencies in `apps/api`.
2. Copy `.env.example` to a local `.env`; never commit the local file.
3. Generate `APP_KEY`.
4. Configure MariaDB credentials.
5. Configure `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS` for the deployed web origin.
6. Generate a strong `STORVIA_SETUP_TOKEN` and keep it outside source control.
7. Run migrations in Laravel maintenance mode, then seed the safe access-control catalog:

```bash
php artisan down
php artisan migrate --force
php artisan db:seed --force
php artisan up
```

8. Verify migration status:

```bash
php artisan migrate:status
```

9. Create the first Super Admin securely with `storvia:make-super-admin` or the protected Initial Setup API.

The repository intentionally ships with no operational user and no fixed password.

## 2. Optional local demo dataset

For a disposable local demonstration only, set the following values in your local `.env`:

```env
STORVIA_STARTER_DEMO_SEED=true
STORVIA_STARTER_DEMO_PASSWORD=<strong-local-password>
```

Then run:

```bash
php artisan db:seed --force
```

The demo seeder refuses production, creates no binary fake uploads, and never embeds the password in source. Disable the flag after seeding.

See [`DEMO.md`](DEMO.md).

## 3. Prepare the Web application

1. Install dependencies in `apps/web` with `npm ci`.
2. Copy `.env.example` to `.env.local`.
3. Set `NEXT_PUBLIC_STORVIA_API_URL` to the deployed API origin.
4. Run `npm run dev` for development or `npm run build && npm run start` for a production build.

## 4. Production security

- Use HTTPS.
- Enable `SESSION_SECURE_COOKIE=true` on HTTPS deployments.
- Configure trusted proxies and hosts only when an explicit reverse proxy is used.
- Enable HSTS only after HTTPS is verified end-to-end.
- Keep application secrets in deployment/environment secret storage, never Git.
- Keep physical storage private and writable by the API process only.
- Keep `STORVIA_STARTER_DEMO_SEED=false` in production.
