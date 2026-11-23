<?php

// Router exclusivo del servidor efímero que utiliza el generador de videos.
$workspace = getenv('VELVET_RECORDING_DIR');
if (PHP_SAPI !== 'cli-server' || ! $workspace || getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== $workspace.'/demo.sqlite') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$static = realpath($root.'/public'.$path);
if ($static && str_starts_with($static, $root.'/public/') && is_file($static)) {
    return false;
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->booted(function () use ($workspace) {
    Illuminate\Support\Facades\Vite::useHotFile($workspace.'/disabled.vite');
    Illuminate\Support\Facades\Http::preventStrayRequests();
    config([
        'filesystems.disks.local.root' => $workspace.'/files',
        'session.files' => $workspace.'/sessions',
    ]);

    $role = $_COOKIE['velvet_demo_role'] ?? null;
    if (in_array($role, ['admin', 'model', 'monitor'], true)) {
        $identities = json_decode(file_get_contents($workspace.'/fixtures.json'), true)['users'];
        Illuminate\Support\Facades\Auth::onceUsingId($identities[$role]);
    }
});

$app->handleRequest(Illuminate\Http\Request::capture());
