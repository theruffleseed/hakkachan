# Deploying hakkachan.my

**One method. Do not invent another. Do not hand-assemble the zip.**

## Automated (normal case)

Push to `main` and GitHub Actions does the whole job:

1. Runs `bin/build-deploy.sh` — builds `hakkachan-deploy.zip` from the pushed
   commit and smoke-tests it (it will not finish unless the artifact actually
   serves every page in prod).
2. Uploads the zip to the account home (`/home/hakkacha/hakkachan-deploy.zip`)
   over FTP.
3. Calls `https://hakkachan.my/deploy.php?token=<DEPLOY_TOKEN>&zip=hakkachan-deploy.zip`
   — the hook extracts the zip into the account home (same as a manual File
   Manager extract), clears `var/cache`, and runs pending migrations.

The workflow needs four repository secrets (Settings → Secrets and variables →
Actions):

| Secret        | Value                                        |
|---------------|----------------------------------------------|
| `FTP_HOST`    | your FTP host (e.g. `ftp.hakkachan.my`)      |
| `FTP_USER`    | your cPanel FTP username (`hakkacha`)        |
| `FTP_PASSWORD`| your cPanel FTP password                     |
| `DEPLOY_TOKEN`| `d381cae54ba1f412189df83f9c85b23b38a53555`   |

If the FTP account roots somewhere other than the account home, add `FTP_PATH`
(e.g. `/subdir`) as a fifth secret.

## Manual (fallback — old flow, still works)

```bash
cd web && ./bin/build-deploy.sh
```

That produces `hakkachan-deploy.zip` in the repo root. Then:

1. Upload `hakkachan-deploy.zip` to `/home/hakkacha` (the account home, one
   level **above** `public_html`).
2. Extract it there, overwriting. **Nothing is moved by hand afterwards.**
3. Run the deploy hook:
   `https://hakkachan.my/deploy.php?token=d381cae54ba1f412189df83f9c85b23b38a53555`

Always print that URL in full, token included. Never a placeholder.

## First CI deploy

The server's `deploy.php` only gained the `?zip=` extraction step when that
change shipped. Until then, run **one** manual deploy (above) from the current
`main`; afterwards every push deploys automatically.

## The server

cPanel shared hosting, account home `/home/hakkacha`, **no SSH, no console**.
The only way to run a command is `deploy.php` (clears `var/cache`, runs pending
migrations).

```
/home/hakkacha/
├── public_html/                  docroot — index.php, deploy.php, .htaccess, assets/
├── vendor/ src/ config/ templates/ migrations/ bin/ assets/ var/
├── hakkachan-deploy.zip          uploaded by CI, extracted by the hook
├── .env                          shipped in the zip, APP_ENV=prod
└── .env.local                    NEVER shipped — secrets, see below
```

App files sit **directly in the home directory** because
`public_html/index.php` resolves the app root as `dirname(__DIR__)`.

`/home/hakkacha/hakkachan_app/` is a **stale copy** from an older deploy scheme.
It is not the app root, whatever its name suggests. Assuming otherwise took the
live site down on 2026-08-01.

## Never overwritten

`deploy.php` skips these on extraction, and the build never packages them:

- `.env.local` — `APP_ENV=prod`, `APP_SECRET`, Stripe keys, `DEPLOY_TOKEN`.
  Lives only on the server. Overwriting it breaks checkout and the deploy hook.
- `var/*.db` — the reservations, SQLite. Overwriting it destroys every booking.

## Host quirks, each learned the hard way

- **`highlight_file` is disabled.** Symfony's HTML debug error page calls it and
  crashes, masking the real exception. `APP_DEBUG=1` is useless here — and it
  publicly exposes source code. Read the log instead.
- **prod logs go to `var/log/prod.log`** (fixed in 7715548). They previously
  went to `php://stderr`, which this host discards, so both the log file and
  cPanel → Errors sat empty during an outage.
- **`var/tailwind/app.built.css` must be in the zip.** The Tailwind bundle reads
  it at runtime and throws if absent, 500ing every page that extends
  `base.html.twig` — while the build looks clean. `bin/build-deploy.sh` copies
  it and the smoke test catches its absence.
- **A production artifact must boot prod.** The packaged `.env` sets
  `APP_ENV=prod`; dev mode against a `--no-dev` vendor dies on a missing
  DebugBundle.

## Verifying a deploy

Check HTTP status and `<title>`. Do **not** grep the body for page text — the
debug error page embeds template source, so "HAKKACHAN" appears on a page that
is actually broken. That false positive led to reporting a downed site as up.

```bash
for p in / /reserve /visit /legal/booking-terms; do
  curl -sS -o /tmp/p.html -w "$p -> %{http_code} " "https://hakkachan.my$p"
  grep -oE '<title>[^<]*</title>' /tmp/p.html | head -1
done
```
