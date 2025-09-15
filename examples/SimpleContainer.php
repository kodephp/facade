<?php

declare(strict_types=1);

namespace Examples;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class SimpleContainer implements ContainerInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $entries = [];

    /**
     * @param string $id
     * @param mixed $value
     * @return void
     */
    public function set(string $id, $value): void
    {
        $this->entries[$id] = $value;
    }

    /**
     * @inheritDoc
     */
    public function get(string $id)
    {
        if (!$this->has($id)) {
            throw new class($id) extends \Exception implements NotFoundExceptionInterface {
                public function __construct(string $id) {
                    parent::__construct("Service not found: {$id}");
                }
            };
        }

        return $this->entries[$id];
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }
}