# STORVIA Starter Web

Next.js frontend for STORVIA Starter Kit.

## Requirements

- Node.js 24
- npm

## Local setup

```bash
npm ci
cp .env.example .env.local
npm run dev
```

Set `NEXT_PUBLIC_STORVIA_API_URL` to the Laravel API origin.

## Verification

```bash
node --test "tests/frontend-contracts/*.test.mjs"
npm run lint
npx tsc --noEmit
npm run build
```

Do not commit `.env*`, `.next`, `node_modules`, build output, or local deployment metadata.
