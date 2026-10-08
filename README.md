# Convene

Convene is a management information system for NGOs. It covers beneficiaries,
programs and projects, geography, and configurable data-collection forms, with a
web admin and an offline-capable mobile app.

## Repository layout

| Path | Description |
|---|---|
| `apps/api` | Laravel 12 REST API (PHP 8.2+, Sanctum auth) |
| `apps/web` | Web admin (React 19, Vite, TypeScript) |
| `apps/mobile` | Mobile app (React Native 0.86) |
| `packages/shared-types` | Shared TypeScript types |
| `packages/widget-schema` | Form widget schema shared by web and mobile |
| `packages/ui-tokens` | Theme tokens and branding defaults |
| `deploy/shared-hosting` | Deployment guide for cPanel/Plesk hosting |
| `scripts` | Setup scripts |

## Requirements

- Node.js 22.11 or later
- PHP 8.2 or later and Composer
- MySQL or MariaDB
- For mobile: Android Studio and/or Xcode

## Getting started

Install JavaScript dependencies from the repository root:

```bash
npm install
```

### API

```bash
cd apps/api
composer setup
composer dev
```

`composer setup` installs dependencies, creates `.env` from `.env.example`,
generates the app key and runs migrations. Set the database credentials in
`.env` before running it.

### Web

```bash
npm run web:dev
```

### Mobile

```bash
npm run mobile:start
npm run android --workspace apps/mobile
npm run ios --workspace apps/mobile
```

## Configuration

Hosting-specific settings are read from `apps/api/config/convene.php`:

| Variable | Values |
|---|---|
| `CONVENE_HOSTING_MODE` | `vm` or `shared` |
| `CONVENE_DEFAULT_DISK` | `s3_storage` or `local_storage` |
| `CONVENE_QUEUE_MODE` | `worker`, `sync` or `cron-batch` |

## Tests and linting

```bash
cd apps/api && composer test
npm run web:lint
npm run mobile:lint
```

## Deployment

See [deploy/shared-hosting/README.md](deploy/shared-hosting/README.md) for shared hosting.

## License

GNU Affero General Public License v3.0. See [LICENSE](LICENSE).
