<?php
/** Extend the frozen historical suites without changing their assertions. */
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
ob_start();
require __DIR__ . '/run.php';
require __DIR__ . '/alt-compatibility.php';
ob_end_clean();
echo 'PASS ' . $checks . ' assertions including alt/compatibility on PHP ' . PHP_VERSION . $skip . "\n";
