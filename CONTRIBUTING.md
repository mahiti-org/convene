# Contributing to Convene

Thank you for your interest in Convene. By taking part you agree to follow our
[Code of Conduct](CODE_OF_CONDUCT.md).

For larger changes, open an issue first so the approach can be agreed before you
write code. Never include real beneficiary or personal data in issues, pull
requests or test data.

## Local setup

Requirements: Node.js 22.11+, PHP 8.2+ with Composer, MySQL or MariaDB, and
Android Studio or Xcode for mobile.

```bash
npm install

# API
cd apps/api
composer setup
composer dev

# Web
npm run web:dev

# Mobile
npm run mobile:start
```

`composer setup` creates `.env` from `.env.example` and runs migrations. Set the
database credentials in `.env` first.

## Workflow

1. Fork `mahiti-org/convene` and clone your fork.
2. Create a branch from `main`, for example `fix/geography-import`.
3. Make your changes and commit them.
4. Push to your fork and open a pull request against `main`.

## Commit messages

Use a short subject in the imperative mood, for example
`Add beneficiary export` or `Fix geography import validation`.

## Tests and linters

Run these before opening a pull request:

```bash
cd apps/api && composer test
npm run web:lint
npm run mobile:lint
```

## Pull requests

- Describe what changed and why, and link the related issue.
- Keep pull requests small and focused.
- A maintainer will review and merge approved pull requests.

## License

Contributions are licensed under the [GNU AGPL v3.0](LICENSE).
