<?php
require __DIR__ . '/../vendor/autoload.php';

use Nova\Container;
use Nova\Providers\EloquentServiceProvider;

$provider = new EloquentServiceProvider();
$provider->register(Container::getInstance(), [
  'connections' => [
    'default' => [
      'driver' => 'sqlite',
      'database' => __DIR__ . '/database.sqlite',
    ],
  ],
]);

echo "Eloquent booted (if illuminate/database is installed)." . PHP_EOL;



