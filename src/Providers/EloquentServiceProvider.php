<?php
declare(strict_types=1);
namespace Nova\Providers;

use Nova\Contracts\ContainerInterface;

class EloquentServiceProvider
{
    public function register(ContainerInterface $app, array $config = []): void
    {
        if (!class_exists('\\Illuminate\\Database\\Capsule\\Manager')) {
            return;
        }
        $app->singleton('db', function () use ($config) {
            $capsule = new \Illuminate\Database\Capsule\Manager();
            if (!empty($config['connections'])) {
                foreach ($config['connections'] as $name => $connection) {
                    $capsule->addConnection($connection, $name);
                }
            } else {
                $capsule->addConnection([
                    'driver' => getenv('DB_DRIVER') ?: 'sqlite',
                    'database' => getenv('DB_DATABASE') ?: ':memory:',
                ]);
            }
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
            return $capsule;
        });
    }
}



