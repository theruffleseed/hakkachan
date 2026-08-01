<?php

// One-off deploy hook: hit this URL after extracting a new build to clear the
// stale compiled container/cache from the previous deploy, and to run any
// pending Doctrine migrations (no SSH/console access on this host). Protected
// by DEPLOY_TOKEN (set in .env.local on the server, never committed).

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
