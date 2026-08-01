# hakkachan.my — Symfony app

**Deploying: read `DEPLOY.md` first, every time, before touching anything to do
with builds, zips, uploads, paths, or the server.** There is exactly one deploy
method (`bin/build-deploy.sh`) and one server layout. Do not invent a new one,
do not hand-assemble the archive, and do not infer paths from folder names —
getting this wrong has already taken the live site down.

Symfony 7.4, AssetMapper + Tailwind v4, Stimulus, Doctrine/SQLite, Stripe
Checkout. Reservation logic lives in `src/Reservation/` and is unit tested;
`php bin/phpunit` runs in about a second, so run it.
