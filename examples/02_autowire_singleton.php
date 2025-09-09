<?php
require __DIR__ . '/../vendor/autoload.php';

use Nova\Container;
use Nova\Attributes\Singleton;

#[Singleton]
class HttpClient { public function __construct(private Logger $logger) {} }
class Logger {}

$app = Container::getInstance();
$a = $app->make(HttpClient::class);
$b = $app->make(HttpClient::class);
var_dump($a === $b);



