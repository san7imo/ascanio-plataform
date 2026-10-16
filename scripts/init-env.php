<?php

$path = __DIR__.'/../.env';
if (! file_exists($path)) {
    copy(__DIR__.'/../.env.example', $path);
}
$contents = file_get_contents($path);
$contents = preg_replace('/^DB_PASSWORD=$/m', 'DB_PASSWORD='.bin2hex(random_bytes(24)), $contents);
file_put_contents($path, $contents);
chmod($path, 0600);
echo "Local environment prepared; existing values preserved.\n";
