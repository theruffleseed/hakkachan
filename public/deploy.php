<?php

// Deploy hook: hit this URL after a build lands on the server to extract it,
// clear the stale compiled container/cache from the previous deploy, and run
// any pending Doctrine migrations (no SSH/console access on this host).
// Protected by DEPLOY_TOKEN (set in .env.local on the server, never committed).
//
// With ?zip=<name>, the hook first extracts <name> from the account home into
// the app root — this is how GitHub Actions deploys (it FTPs hakkachan-
// deploy.zip to the home dir, then hits this URL). Extracting via the hook
// works because a build zip extracts directly into the account home; without
// the zip param the hook behaves like the old one-off version.

require dirname(__DIR__).'/vendor/autoload.php';

// bootEnv, not loadEnv: it also reads a dumped .env.local.php, which is where
// the values live when the host was set up with `composer dump-env prod`.
(new Symfony\Component\Dotenv\Dotenv())->usePutenv()->bootEnv(dirname(__DIR__).'/.env', 'prod');

$expected = $_ENV['DEPLOY_TOKEN'] ?? '';
$given = $_GET['token'] ?? '';

if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    exit('Forbidden');
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir.'/'.$item;
        is_dir($path) ? rrmdir($path) : unlink($path);
    }
    rmdir($dir);
}

$root = dirname(__DIR__);

// Files that must never be overwritten, however plausible the archive looks.
// The build never packages them, but the live site has already been taken
// down once by a deploy overwriting exactly these.
function isProtected(string $name): bool
{
    return str_starts_with($name, '.env.local')
        || preg_match('#^var/[^/]+\.db($|\.)|^var/share/#', $name) === 1;
}

if (isset($_GET['zip'])) {
    $zipName = basename($_GET['zip']); // no path traversal
    $zipPath = $root.'/'.$zipName;

    if (!is_file($zipPath)) {
        http_response_code(404);
        exit("Zip not found: $zipName\n");
    }

    if (!class_exists(ZipArchive::class)) {
        http_response_code(500);
        exit("ZipArchive extension missing on this host\n");
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        http_response_code(500);
        exit("Cannot open $zipName\n");
    }

    $count = 0;
    for ($i = 0; $i < $zip->numFiles; ++$i) {
        $name = $zip->getNameIndex($i);
        if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/')
            || isProtected($name) || $name === $zipName) {
            continue;
        }
        $zip->extractTo($root, [$name]);
        ++$count;
    }
    $zip->close();

    header('Content-Type: text/plain');
    echo "Extracted $count entries from $zipName.\n\n";
}

rrmdir($root.'/var/cache');
mkdir($root.'/var/cache', 0775, true);

header('Content-Type: text/plain');
echo "Cache cleared.\n\n";

$kernel = new App\Kernel($_ENV['APP_ENV'] ?? 'prod', false);
$application = new Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
$application->setAutoExit(false);

$input = new Symfony\Component\Console\Input\ArrayInput([
    'command' => 'doctrine:migrations:migrate',
    '--no-interaction' => true,
]);
$output = new Symfony\Component\Console\Output\BufferedOutput();
$application->run($input, $output);

echo "Migrations:\n".$output->fetch();
