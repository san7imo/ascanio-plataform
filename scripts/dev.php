<?php

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Process\Process;

$php = escapeshellarg(PHP_BINARY);
$process = new Process([
    'node_modules/.bin/concurrently', '--kill-others',
    '--names=server,queue,vite',
    "$php artisan serve", "$php artisan queue:listen --tries=1 --timeout=60", 'npm run dev',
], dirname(__DIR__), ['PHP_BINARY' => PHP_BINARY]);
$process->setTimeout(null);
exit($process->run(fn ($type, $output) => print ($output)));
