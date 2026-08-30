<?php

namespace Modules\McpServer\Support;

use Mcp\Exception\ContainerException;
use Mcp\Exception\ServiceNotFoundException;
use Psr\Container\ContainerInterface;

/**
 * Small PSR-11 v1 container for FreeScout's pinned interface.
 *
 * MCP SDK 0.8.1's default container declares PSR-11 v2 method signatures even
 * though the package permits PSR-11 v1. Supplying this container avoids loading
 * that class and leaves FreeScout's framework-level PSR contracts untouched.
 */
final class LegacyCompatibleContainer implements ContainerInterface
{
    /** @var array<string, object> */
    private $instances = [];

    /** @var array<string, bool> */
    private $resolving = [];

    /**
     * @param string $id
     * @return mixed
     */
    public function get($id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!is_string($id) || !class_exists($id)) {
            throw new ServiceNotFoundException(sprintf('Class or entry "%s" was not found.', (string) $id));
        }

        if (isset($this->resolving[$id])) {
            throw new ContainerException(sprintf('Circular dependency while resolving "%s".', $id));
        }

        $this->resolving[$id] = true;

        try {
            $reflection = new \ReflectionClass($id);
            if (!$reflection->isInstantiable()) {
                throw new ContainerException(sprintf('Class "%s" is not instantiable.', $id));
            }

            $constructor = $reflection->getConstructor();
            if (null === $constructor) {
                return $this->instances[$id] = $reflection->newInstance();
            }

            $arguments = [];
            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $arguments[] = $this->get($type->getName());
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                } elseif ($parameter->allowsNull()) {
                    $arguments[] = null;
                } else {
                    throw new ContainerException(sprintf(
                        'Parameter $%s on "%s" cannot be resolved.',
                        $parameter->getName(),
                        $id
                    ));
                }
            }

            return $this->instances[$id] = $reflection->newInstanceArgs($arguments);
        } catch (\ReflectionException $exception) {
            throw new ContainerException(sprintf('Could not reflect "%s".', $id), 0, $exception);
        } finally {
            unset($this->resolving[$id]);
        }
    }

    /** @param string $id */
    public function has($id)
    {
        return is_string($id) && (isset($this->instances[$id]) || class_exists($id));
    }

    public function set(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }
}
