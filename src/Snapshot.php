<?php

declare(strict_types=1);

namespace Roxblnfk\DeadLink;

use ArrayAccess;
use Closure;
use Countable;
use IteratorAggregate;
use WeakReference;

/**
 * @psalm-type ObjectProperties = array<int<0, max>, array{object, non-empty-string}>
 * @psalm-type ObjectReferencesList = array<null|array{WeakReference, non-empty-string}>
 *
 * @implements IteratorAggregate<object, ObjectReferencesList>
 */
final class Snapshot implements ArrayAccess, IteratorAggregate, Countable
{
    /**
     * List per each object contains {@see null} or {@see array}:
     *  - weak reference ot parent
     *  - parent class name with path to the value (property; keys if it is an array)
     * @var \WeakMap<object, ObjectReferencesList>
     */
    private \WeakMap $map;
    /** @var \WeakMap<object, int|non-empty-string> */
    private \WeakMap $rootObjects;

    private function __construct(object ...$objects) {
        $this->map = new \WeakMap();
        $this->rootObjects = new \WeakMap();
        foreach ($objects as $key => $object) {
            $this->rootObjects[$object] = $key;
        }
    }

    public function __clone()
    {
        $this->map = clone $this->map;
    }

    public static function make(object ...$objects): self
    {
        $snap = new self(...$objects);
        // Add all objects to the map
        \array_walk($objects, static fn (object $obj) => $snap->addObjectLink($obj));

        // Deep mapping
        foreach ($objects as $key => $object) {
            $snap->snapObject($object, \is_string($key) ? $key : null);
        }

        return $snap;
    }

    /**
     * @param-assert object $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        if (!\is_object($offset)) {
            throw new \InvalidArgumentException('Offset must be object only.');
        }
        return $this->map->offsetExists($offset);
    }

    /**
     * @param-assert object $offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!\is_object($offset)) {
            throw new \InvalidArgumentException('Offset must be object only.');
        }
        return $this->map->offsetGet($offset);
    }

    /**
     * @param-assert object $offset
     * @param-assert array $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!\is_object($offset)) {
            throw new \InvalidArgumentException('Offset must be object only.');
        }
        if (!\is_array($value)) {
            throw new \InvalidArgumentException('Value must be array.');
        }
        $this->map[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        if (!\is_object($offset)) {
            throw new \InvalidArgumentException('Offset must be object only.');
        }
        $this->map->offsetUnset($offset);
    }

    /**
     * @return \Iterator<object, ObjectReferencesList>
     */
    public function getIterator(): \Iterator
    {
        return $this->map->getIterator();
    }

    public function count(): int
    {
        return $this->map->count();
    }

    /**
     * Snap the object and all its object-properties
     *
     * @param null|non-empty-string $alias
     */
    public function snapObject(object $object, ?string $alias): void
    {
        /** @var array<array{object, non-empty-string, ObjectProperties}> $collection */
        $collection = [[$object, $alias ?? $object::class, $this->iterateObject($object)]];
        while ($collection !== []) {
            [$parent, $parentPath, $objectProperties] = \array_shift($collection);
            foreach ($objectProperties as $property) {
                [$object, $objPath] = $property;
                $objPath = $parentPath . '.' . $objPath;

                // Object is already in the map
                if ($this->offsetExists($object)) {
                    // todo add to cyclic
                    $this->addObjectLink($object, $parent, $objPath);
                    continue;
                }

                // New object
                $this->addObjectLink($object, $parent, $objPath);
                $collection[] = [$object, $objPath, $this->iterateObject($object)];
            }
        }
    }

    /**
     * @param object ...$addObjects Additional objects to add to the snapshot
     */
    public function updateMap(object ...$addObjects): void
    {
        $rootObjects = [];
        /**
         * @var object $object
         * @var int|string $key
         */
        foreach ($this->rootObjects as $object => $key) {
            $rootObjects[\is_int($key) ? $object::class : $key] = $object;
        }
        // Make new snapshot
        $snapshot = self::make(...$rootObjects);

        // Merge new snapshot into current one
        foreach ($snapshot as $object => $references) {
            $this->map[$object] = $references;
            $this->map->offsetSet(
                $object,
                $this->map->offsetExists($object)
                    ? $this->mergeReferences($this->map->offsetGet($object), $references)
                    : $references,
            );
        }

        // Add additional objects
        foreach ($addObjects as $key => $object) {
            if ($this->rootObjects->offsetExists($object)) {
                continue;
            }
            $this->rootObjects->offsetSet($object, $key);
            $this->addObjectLink($object);
            $this->snapObject($object, \is_string($key) ? $key : null);
        }
    }

    public function clear(): void
    {
        foreach ($this->map as $object => $references) {
            $changed = false;
            foreach ($references as $k => $reference) {
                /** @var null|array{WeakReference, non-empty-string} $reference */
                if ($reference === null) {
                    continue;
                }
                if ($reference[0]->get() === null) {
                    $changed = true;
                    unset($references[$k]);
                }
            }
            if ($changed) {
                $this->map->offsetSet($object, $references);
            }
        }
    }

    /**
     * @param null|non-empty-string $path
     */
    private function addObjectLink(object $object, ?object $parent = null, ?string $path = null): void
    {
        $value = $this->map[$object] ?? [];
        $value[] = $parent === null ? null : [WeakReference::create($parent), $path];
        $this->map->offsetSet($object, $value);
    }

    /**
     * Get all object-properties from the object.
     *
     * @return ObjectProperties
     */
    private function iterateObject(object $object): array
    {
        if ($object::class === Closure::class) {
            return [];
        }
        $result = [];
        $properties = (array)$object;

        foreach ($properties as $key => $value) {
            $rPos = \strrpos($key, "\0");
            $key = \substr($key, $rPos === false ? 0 : $rPos + 1);

            if (\is_object($value)) {
                $result[] = [$value, $key];
            }
            // todo array
        }
        return $result;
    }

    /**
     * @param ObjectReferencesList $ref1
     * @param ObjectReferencesList $ref2
     *
     * @return ObjectReferencesList
     */
    private function mergeReferences(array $ref1, array $ref2): array
    {
        $nullable = false;
        $column = [];
        $result = $ref1;
        foreach ($ref1 as $reference) {
            if ($reference === null) {
                $nullable = true;
                continue;
            }
            $column[] = $reference;
        }

        foreach ($ref2 as $ref) {
            if ($ref === null) {
                if (!$nullable) {
                    $nullable = true;
                    $result[] = null;
                }
                continue;
            }
            if (!\in_array($ref[0], $column, true)) {
                $result[] = $ref;
            }
        }

        return $result;
    }
}
