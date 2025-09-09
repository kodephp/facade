<?php
require __DIR__ . '/../vendor/autoload.php';

use Nova\Support\Runtime;
use Nova\Context\ContextStorage;

echo "Runtime: " . Runtime::getRuntime() . PHP_EOL;
echo "Context ID: " . ContextStorage::id() . PHP_EOL;
echo "Fibers: " . (Runtime::hasFibers() ? 'yes' : 'no') . PHP_EOL;



