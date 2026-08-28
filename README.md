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

## Reservations (`/reserve`)

- Guest count is 2 up to however many seats are left that night (capacity 18).
- Seat-left copy in the date dropdown only when fewer than 4 remain.
- Sold-out nights stay in the list as `(Fully Booked)`, greyed, not selectable.
- Bookings close 2 days before the seating (no Wednesday/Thursday booking for
  Friday). Those dates stay in the dropdown, greyed, not selectable.
- Checkout uses the same rules, so a greyed-out date cannot be paid for.

## Deploy

**Read [`DEPLOY.md`](DEPLOY.md) before touching builds, zips, uploads, paths,
or the server — there is exactly one deploy method and one server layout.**

cPanel shared hosting, account `/home/hakkacha`. **No SSH** (port 22 refused).
The only remote access is FTP plus `deploy.php`.

**Push to `main`.** GitHub Actions builds `hakkachan-deploy.zip`, FTPs it to
the account home, FTPs `public/deploy.php` into `public_html/` (the live hook
used to ignore `?zip=` and only clear cache — a green Actions run is not
proof the files landed), then hits the extract hook. Confirm a deploy by
`Extracted N entries` in the hook log, then check HTTP status and `<title>`
on live pages.

Manual fallback:

```bash
./bin/build-deploy.sh
```

That smoke-tests the zip. Upload to `/home/hakkacha`, extract there, then
https://hakkachan.my/deploy.php?token=d381cae54ba1f412189df83f9c85b23b38a53555
