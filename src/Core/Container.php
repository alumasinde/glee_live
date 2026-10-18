<?php

namespace App\Core;

use Exception;

class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $key, callable $resolver)
    {
        $this->bindings[$key] = $resolver;
    }

    public function singleton(string $key, callable $resolver)
    {
        $this->bindings[$key] = function ($container) use ($resolver, $key) {
            if (!isset($this->instances[$key])) {
                $this->instances[$key] = $resolver($container);
            }
            return $this->instances[$key];
        };
    }

    public function make(string $key)
    {
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        if (isset($this->bindings[$key])) {
            return $this->bindings[$key]($this);
        }

        // auto resolve
        if (!class_exists($key)) {
            throw new Exception("Cannot resolve {$key}");
        }

        $reflection = new \ReflectionClass($key);

        if (!$reflection->getConstructor()) {
            return new $key;
        }

        $params = [];

foreach ($reflection->getConstructor()->getParameters() as $param) {
    $type = $param->getType();

    // Built-in types (string, int, ...) can't come from the container:
    // use the default value if there is one.
    if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
        $params[] = $this->make($type->getName());
        continue;
    }

    if ($param->isDefaultValueAvailable()) {
        $params[] = $param->getDefaultValue();
        continue;
    }

    if ($type && $type->allowsNull()) {
        $params[] = null;
        continue;
    }

    $name = $type ? (string) $type : $param->getName();
    throw new Exception("Cannot resolve parameter {$name}");
}

return $reflection->newInstanceArgs($params);
    }
}
