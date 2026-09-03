<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppWorkflows\Actions;

use Hwkdo\IntranetAppWorkflows\Actions\Contracts\WorkflowActionInterface;
use InvalidArgumentException;

final class ActionRegistry
{
    /**
     * @param  array<string, class-string<WorkflowActionInterface>>  $handlers
     */
    public function __construct(
        private array $handlers = [],
    ) {}

    /**
     * @param  array<string, class-string<WorkflowActionInterface>>  $handlers
     */
    public function registerMany(array $handlers): void
    {
        foreach ($handlers as $key => $class) {
            $this->handlers[$key] = $class;
        }
    }

    public function register(string $key, string $handlerClass): void
    {
        $this->handlers[$key] = $handlerClass;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->handlers);
    }

    public function resolve(string $key): WorkflowActionInterface
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Unknown workflow action handler [{$key}].");
        }

        $class = $this->handlers[$key];

        return app($class);
    }

    /**
     * @return array<string, class-string<WorkflowActionInterface>>
     */
    public function all(): array
    {
        return $this->handlers;
    }
}
