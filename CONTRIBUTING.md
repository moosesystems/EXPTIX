# Contributing to ExpTix

Thank you for helping. Bug reports, fixes, translations, integrations and documentation are all welcome.

## Ground rules

- By contributing, you agree that your contribution is licensed under the GNU AGPL v3 or later, the same as the project.
- Never include real guest, staff or company data in issues, screenshots, tests or seed data. Use fictional names and `example.com` addresses.
- Never commit secrets. `app/config/local.php` and `app/data/` are ignored for a reason.

## Development setup

```bash
php app/bin/cli.php migrate
php app/bin/cli.php seed
php -S localhost:8080 -t public public/index.php
```

`php app/bin/cli.php seed --reset` gives you a clean demo database at any time.

## Code style

- PHP 8.1+, `declare(strict_types=1)`, no framework and no Composer dependencies in the core. Keep it deployable by copying files.
- Front end: plain JavaScript, no build step.
- All SQL goes through `Db` with bound parameters and backtick identifiers, so it runs on both SQLite and MySQL.
- New tables and columns go in `Schema::tables()`. `migrate` only ever adds; never write a migration that drops or renames.
- Every state change that matters for accountability calls `Audit::log`.
- Check permissions on the server for every endpoint. The UI hiding a button is not access control.

## Pull requests

1. Open an issue first for anything larger than a small fix, so we can agree the approach.
2. Keep each pull request to one change, and describe how you tested it on SQLite and, where it matters, MySQL.
3. Run `php -l` on changed PHP files and click through the screens you touched as at least two roles.
