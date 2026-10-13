# Contributing to The Velvet Studio

Thank you for helping improve The Velvet Studio. This guide explains how to
propose a safe, reviewable contribution.

Spanish version: [docs/CONTRIBUIR.es.md](docs/CONTRIBUIR.es.md).

## Before you start

- Read the platform guide in [docs/guia-plataforma.md](docs/guia-plataforma.md).
- Follow the project conventions in [AGENTS.md](AGENTS.md), especially the
  Driver.js guided-tour requirement for new user-facing workflows.
- Never commit `.env`, credentials, API keys, personal data, uploaded files or
  production database exports.

## Local setup

Requirements: PHP 8.2+, Composer, Node.js 22+ and a configured database.

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm ci
npm run dev
```

On macOS or Linux, replace `copy` with `cp`.

For test accounts, run:

```bash
php artisan db:seed --class=TestUsersSeeder
```

## Contribution workflow

1. Create a focused branch from the current default branch.
2. Keep the change small and explain the user-facing impact in the pull
   request.
3. Add or update feature/regression tests when changing permissions, state
   transitions, public links, persisted data or a bug fix.
4. Add or update a contextual Driver.js guide for every relevant new module,
   flow or component.
5. Update `docs/guia-plataforma.md` when operational behavior changes.
6. Run the checks below before opening a pull request.

```bash
php artisan test
npm run build
```

## Pull request checklist

- [ ] The change has a clear, single purpose.
- [ ] Authorization and validation were considered.
- [ ] Responsive behavior was verified without overlapping content.
- [ ] Tests cover the critical path or the regression.
- [ ] Driver.js guidance is included or explicitly not applicable.
- [ ] No secrets, production data or generated `public/build` files are added.
- [ ] Documentation was updated when the team workflow changed.

## Continuous integration

GitHub Actions runs on every push and pull request:

- **Backend · PHP 8.2** validates Composer and runs `php artisan test` using
  isolated SQLite-in-memory tests.
- **Frontend · Node 22** installs locked dependencies and runs `npm run build`.

Both checks must pass before merging when branch protection is enabled.

## Reporting bugs and security issues

Use an issue for reproducible, non-sensitive bugs and include steps, expected
behavior, actual behavior and screenshots when useful.

Do not publish credentials, personally identifiable information, signed links
or security vulnerabilities in public issues. Follow [SECURITY.md](SECURITY.md)
and contact a repository maintainer privately for security reports.

## License and branding

Source code contributions are licensed under the [MIT License](LICENSE).
The Velvet Studio name, logos and other brand assets are not granted by that
license; see [TRADEMARKS.md](TRADEMARKS.md).
