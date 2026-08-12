# hakkachan.my — Symfony app

**Deploying: read `DEPLOY.md` first, every time, before touching anything to do
with builds, zips, uploads, paths, or the server.** There is exactly one deploy
method and one server layout. Do not invent a new one, do not hand-assemble the
archive, and do not infer paths from folder names — getting this wrong has
already taken the live site down.

Deploys are automatic: pushing to `main` triggers GitHub Actions, which runs
`bin/build-deploy.sh`, FTPs the zip to cPanel and fires the `deploy.php` hook
(`?zip=` extracts it, clears cache, runs migrations). Only git-committed files
ship — commit everything, including any built assets.

Symfony 7.4, AssetMapper + Tailwind v4, Stimulus, Doctrine/SQLite, Stripe
Checkout. Reservation logic lives in `src/Reservation/` and is unit tested;
`php bin/phpunit` runs in about a second, so run it.
