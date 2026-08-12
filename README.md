# hakkachan.my

Symfony 7.4 app for Hakkachan, an 18-seat Hakka dining residency in Kuala
Lumpur. AssetMapper + Tailwind v4, Stimulus, Doctrine, Stripe Checkout for
reservations.

## Local dev

```bash
composer install
php bin/console doctrine:migrations:migrate
php bin/console tailwind:build
php -S 127.0.0.1:8000 -t public public/index.php
```

Dev defaults to SQLite (`var/data_dev.db`, `.env`) — no extra setup needed.
Reservation logic lives in `src/Reservation/` and is unit tested:

```bash
php bin/phpunit
```

## Deploy

**Read [`DEPLOY.md`](DEPLOY.md) before touching builds, zips, uploads, paths,
or the server — there is exactly one deploy method and one server layout.**

Normal deploys are automatic: **push to `main`** and GitHub Actions builds the
artifact, uploads it via FTP and runs the deploy hook. For the manual fallback:

```bash
./bin/build-deploy.sh
```

This produces `hakkachan-deploy.zip` in the repo root and smoke-tests the
finished artifact before it'll report success.
