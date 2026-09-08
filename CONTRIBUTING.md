# Contributing to STORVIA Starter Kit

Thanks for helping improve STORVIA Starter Kit.

## Before opening a change

- Keep one pull request focused on one bounded concern.
- Do not weaken backend authorization or security contracts to make a test pass.
- Do not commit `.env` files, credentials, database dumps, uploaded content, runtime logs, or build artifacts.
- Preserve MariaDB compatibility.

## Verification

### API

From `apps/api`:

```bash
vendor/bin/pint --test
php artisan test
```

### Web

From `apps/web`:

```bash
node --test "tests/frontend-contracts/*.test.mjs"
npm run lint
npx tsc --noEmit
npm run build
```

Run the narrowest relevant tests first, then the broader suite before submitting a substantial change.

## Security issues

Do not file exploitable security findings as public issues. Follow [`SECURITY.md`](SECURITY.md).
