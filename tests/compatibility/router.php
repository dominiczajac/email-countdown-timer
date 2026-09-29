<?php
// Only used by the loopback CLI server; allows real WordPress pretty-permalink routing.
$root = getenv('ECD_COMPAT_WP');
if (getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || !$root) { exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($root . $path);
if ($file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) { return false; }
require $root . '/index.php';
