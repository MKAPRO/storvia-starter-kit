# Optional Demo Dataset

The public Starter Kit ships with **no operational users and no fixed password**.

Normal `php artisan db:seed --force` seeds only the access-control catalog and creates no user.

## Enable demo data locally

Set these values only in the local API `.env`:

```env
STORVIA_STARTER_DEMO_SEED=true
STORVIA_STARTER_DEMO_PASSWORD=<strong-local-password>
```

Then run:

```bash
php artisan db:seed --force
```

The demo seeder creates:

- one Super Admin: `demo.admin` / `demo.admin@storvia.test`
- Operations and Finance departments
- one personal FileSpace and two department FileSpaces
- sample folders only; no fake binary uploads or storage objects
- completed demo installation metadata

The password is never embedded in source control. The seeder is idempotent and refuses to run in `production`.

After demo seeding, set `STORVIA_STARTER_DEMO_SEED=false` again.
