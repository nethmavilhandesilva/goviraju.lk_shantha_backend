<?php
// Uploaded by the GitHub Actions pipeline next to deploy.zip.
// Extracts the release, clears caches and runs migrations.

$token = '__DEPLOY_TOKEN__';

header('Content-Type: text/plain');

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || ! hash_equals($token, $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '')) {
    http_response_code(403);
    exit("Forbidden\n");
}

set_time_limit(600);
ignore_user_abort(true);

$base = __DIR__;
$zipFile = $base . '/deploy.zip';

if (! is_file($zipFile)) {
    http_response_code(500);
    exit("deploy.zip not found\n");
}

$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) {
    http_response_code(500);
    exit("Could not open deploy.zip\n");
}
if (! $zip->extractTo($base)) {
    http_response_code(500);
    exit("Could not extract deploy.zip\n");
}
$zip->close();
unlink($zipFile);
echo "Extracted release\n";

// Stale package/service/config caches can reference dev-only classes
foreach (glob($base . '/bootstrap/cache/*.php') as $file) {
    unlink($file);
}

foreach (['storage/framework/cache/data', 'storage/framework/sessions',
          'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
    if (! is_dir($base . '/' . $dir)) {
        mkdir($base . '/' . $dir, 0775, true);
    }
}

try {
    require $base . '/vendor/autoload.php';
    $app = require $base . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    $kernel->call('migrate', ['--force' => true]);
    echo $kernel->output();

    $kernel->call('optimize:clear');
    echo $kernel->output();

    if (! file_exists($base . '/public/storage')) {
        $kernel->call('storage:link');
        echo $kernel->output();
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('Artisan failed: ' . $e->getMessage() . "\n");
}

echo "Deploy finished\n";
