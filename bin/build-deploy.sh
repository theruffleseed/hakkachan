#!/usr/bin/env bash
# Builds hakkachan-deploy.zip from the current commit.
#
# The archive extracts directly into the account's home directory on the host
# (/home/hakkacha), which is one level above the docroot:
#
#   ~/public_html/   front controller, deploy.php, .htaccess, hashed assets
#   ~/vendor, src, config, templates, migrations, bin, assets, .env, var/
#
# public_html/index.php resolves the app root as dirname(__DIR__), so the app
# files MUST sit directly in the home directory — not in a subfolder. Nothing
# is moved by hand after extracting.
#
# Never packaged: .env.local (secrets, APP_ENV=prod, DEPLOY_TOKEN) and
# var/*.db (the reservations). Both stay on the server and survive an extract.
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
out_zip="${1:-$repo/../hakkachan-deploy.zip}"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

stage="$work/stage"
tree="$work/tree"
mkdir -p "$stage" "$tree/public_html"

git -C "$repo" archive HEAD | tar -x -C "$stage"

cd "$stage"
APP_ENV=prod composer install --no-dev --optimize-autoloader --no-interaction --no-progress >/dev/null

# This is a production artifact: boot prod even if the host has no .env.local.
# Booting dev against --no-dev vendor is a 500 (missing DebugBundle).
sed -i 's/^APP_ENV=.*/APP_ENV=prod/' .env

mkdir -p var
cp -r "$repo/var/tailwind" var/tailwind 2>/dev/null || true
APP_ENV=prod php bin/console tailwind:build --minify >/dev/null
APP_ENV=prod php bin/console asset-map:compile >/dev/null

cp -a "$stage/public/." "$tree/public_html/"
for item in assets bin composer.json composer.lock config .editorconfig .env .gitignore \
            importmap.php migrations src symfony.lock templates translations vendor; do
    cp -a "$stage/$item" "$tree/"
done
mkdir -p "$tree/var/cache" "$tree/var/log"

rm -f "$out_zip"
(cd "$tree" && zip -rqX "$out_zip" .)

echo "Built $out_zip"
unzip -p "$out_zip" .env | grep -E '^(APP_ENV|DEFAULT_URI)='
echo "$(unzip -Z1 "$out_zip" | wc -l) entries"
