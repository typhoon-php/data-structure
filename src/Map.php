<?php

declare(strict_types=1);

namespace Typhoon\DataStructures;

/**
 * @api
 * @template-covariant K
 * @template-covariant V
 * @implements \IteratorAggregate<K, V>
 * @implements \ArrayAccess<mixed, mixed>
 * @psalm-consistent-templates
 * @psalm-suppress InvalidTemplateParam
 */
abstract class Map implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * @template NK
     * @template NV
     * @param iterable<NK, NV>|\Closure(): iterable<NK, NV> $values
     * @return self<NK, NV>
     */
    public static function of(iterable|\Closure $values = []): self
    {
        return MutableMap::of($values);
    }

    /**
     * @no-named-arguments
     * @template NK
     * @template NV
     * @param KVPair<NK, NV> ...$kvPairs
     * @return self<NK, NV>
     */
    public static function fromPairs(KVPair ...$kvPairs): self
    {
        return MutableMap::fromPairs(...$kvPairs);
    }

    /**
     * @template NK
     * @template NV
     * @param iterable<NK> $keys
     * @param callable(NK): NV $value
     * @return self<NK, NV>
     */
    public static function fromKeys(iterable $keys, callable $value): self
    {
        return MutableMap::fromKeys($keys, $value);
    }

    /**
     * @template NK
     * @template NV
     * @param iterable<NV> $values
     * @param callable(NV): NK $key
     * @return self<NK, NV>
     */
    public static function fromValues(iterable $values, callable $key): self
    {
        return MutableMap::fromValues($values, $key);
    }

    /**
     * @template NK
     * @template NV
     * @param NK $key
     * @param NV $value
     * @return static<K|NK, V|NV>
     */
    abstract public function with(mixed $key, mixed $value): static;

    /**
     * @no-named-arguments
     * @template NK
     * @template NV
     * @param KVPair<NK, NV> ...$kvPairs
     * @return static<K|NK, V|NV>
     */
    abstract public function withPairs(KVPair ...$kvPairs): static;

    /**
     * @template NK
     * @template NV
     * @param iterable<NK, NV>|\Closure(): iterable<NK, NV> $values
     * @return static<K|NK, V|NV>
     */
    final public function withAll(iterable|\Closure $values): static
    {
        if ($values instanceof \Closure) {
            $values = $values();
        }

        if ($values === []) {
            return $this;
        }

        return $this->doWithAll($values);
    }

    /**
     * @template NK
     * @template NV
     * @param iterable<NK, NV> $values
     * @return static<K|NK, V|NV>
     */
    abstract protected function doWithAll(iterable $values): static;

    /**
     * @no-named-arguments
     * @return static<K, V>
     */
    abstract public function without(mixed ...$keys): static;

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @return non-negative-int
     */
    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    /**
     * @return ($key is K ? bool : false)
     */
    abstract public function contains(mixed $key): bool;

    /**
     * @template D
     * @param D $default
     * @return ($key is K ? V|D : D)
     */
    final public function get(mixed $key, mixed $default = null): mixed
    {
        return $this->getOr($key, static fn(): mixed => $default);
    }

    /**
     * @template D
     * @param callable(): D $or
     * @return ($key is K ? V|D : D)
     */
    abstract public function getOr(mixed $key, callable $or): mixed;

    /**
     * @return ?KVPair<K, V>
     */
    public function first(): ?KVPair
    {
        return $this->findFirst(static fn(): bool => true);
    }

    /**
     * @return ?KVPair<K, V>
     */
    public function last(): ?KVPair
    {
        $started = false;

        foreach ($this->getIterator() as $key => $value) {
            $started = true;
        }

        if ($started) {
            /** @psalm-suppress PossiblyUndefinedVariable */
            return new KVPair($key, $value);
        }

        return null;
    }

    /**
     * @param callable(V): bool $predicate
     * @return ?KVPair<K, V>
     */
    public function findFirst(callable $predicate): ?KVPair
    {
        foreach ($this->getIterator() as $key => $value) {
            if ($predicate($value)) {
                return new KVPair($key, $value);
            }
        }

        return null;
    }

    /**
     * @param callable(K, V): bool $predicate
     * @return ?KVPair<K, V>
     */
    public function findFirstKV(callable $predicate): ?KVPair
    {
        foreach ($this->getIterator() as $key => $value) {
            if ($predicate($key, $value)) {
                return new KVPair($key, $value);
            }
        }

        return null;
    }

    /**
     * @param callable(V): bool $predicate
     */
    public function any(callable $predicate): bool
    {
        foreach ($this->getIterator() as $value) {
            if ($predicate($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param callable(K, V): bool $predicate
     */
    public function anyKV(callable $predicate): bool
    {
        foreach ($this->getIterator() as $key => $value) {
            if ($predicate($key, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param callable(V): bool $predicate
     */
    public function all(callable $predicate): bool
    {
        foreach ($this->getIterator() as $value) {
            if (!$predicate($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param callable(K, V): bool $predicate
     */
    public function allKV(callable $predicate): bool
    {
        foreach ($this->getIterator() as $key => $value) {
            if (!$predicate($key, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @template R
     * @param callable(V|R, V): R $operation
     * @return V|R
     */
    public function reduce(callable $operation): mixed
    {
        return $this->reduceKV(
            /**
             * @param V|R $accumulator
             * @param V $value
             */
            static fn(mixed $accumulator, mixed $key, mixed $value): mixed => $operation($accumulator, $value),
        );
    }

    /**
     * @template R
     * @param callable(V|R, K, V): R $operation
     * @return V|R
     */
    public function reduceKV(callable $operation): mixed
    {
        $started = false;
        /** @var V|R */
        $accumulator = null;

        foreach ($this->getIterator() as $key => $value) {
            if ($started) {
                $accumulator = $operation($accumulator, $key, $value);
            } else {
                $started = true;
                $accumulator = $value;
            }
        }

        if ($started) {
            return $accumulator;
        }

        throw new \RuntimeException('Empty map');
    }

    /**
     * @template I
     * @template R
     * @param I $initial
     * @param callable(I|R, V): R $operation
     * @return I|R
     */
    public function fold(mixed $initial, callable $operation): mixed
    {
        return $this->foldKV(
            $initial,
            /**
             * @param I|R $accumulator
             * @param V $value
             */
            static fn(mixed $accumulator, mixed $key, mixed $value): mixed => $operation($accumulator, $value),
        );
    }

    /**
     * @template I
     * @template R
     * @param I $initial
     * @param callable(I|R, K, V): R $operation
     * @return I|R
     */
    public function foldKV(mixed $initial, callable $operation): mixed
    {
        foreach ($this->getIterator() as $key => $value) {
            $initial = $operation($initial, $key, $value);
        }

        return $initial;
    }

    /**
     * @param callable(V): bool $predicate
     * @return static<K, V>
     */
    public function filter(callable $predicate): static
    {
        return $this->filterKV(
            /** @param V $value */
            static fn(mixed $key, mixed $value): bool => $predicate($value),
        );
    }

    /**
     * @param callable(K, V): bool $predicate
     * @return static<K, V>
     */
    abstract public function filterKV(callable $predicate): static;

    /**
     * @template NV
     * @param callable(V): NV $mapper
     * @return static<K, NV>
     */
    public function map(callable $mapper): static
    {
        return $this->mapKV(
            /** @param V $value */
            static fn(mixed $key, mixed $value): mixed => $mapper($value),
        );
    }

    /**
     * @template NV
     * @param callable(K, V): NV $mapper
     * @return static<K, NV>
     */
    abstract public function mapKV(callable $mapper): static;

    /**
     * @template NK
     * @param callable(V): NK $mapper
     * @return static<NK, V>
     */
    public function mapKey(callable $mapper): static
    {
        return $this->mapKeyKV(
            /** @param V $value */
            static fn(mixed $key, mixed $value): mixed => $mapper($value),
        );
    }

    /**
     * @template NK
     * @param callable(K, V): NK $mapper
     * @return static<NK, V>
     */
    abstract public function mapKeyKV(callable $mapper): static;

    /**
     * @template NK
     * @template NV
     * @param callable(V): iterable<NK, NV> $mapper
     * @return static<NK, NV>
     */
    public function flatMap(callable $mapper): static
    {
        return $this->flatMapKV(
            /** @param V $value */
            static fn(mixed $key, mixed $value): mixed => $mapper($value),
        );
    }

    /**
     * @template NK
     * @template NV
     * @param callable(K, V): iterable<NK, NV> $mapper
     * @return static<NK, NV>
     */
    abstract public function flatMapKV(callable $mapper): static;

    /**
     * @return static<V, K>
     */
    abstract public function flip(): static;

    // TODO public function reverse(): static;

    /**
     * @return static<K, V>
     */
    final public function sort(): static
    {
        return $this->usortKV(static fn(mixed $key1, mixed $value1, mixed $key2, mixed $value2): int => $value1 <=> $value2);
    }

    /**
     * @return static<K, V>
     */
    final public function sortDesc(): static
    {
        return $this->usortKV(static fn(mixed $key1, mixed $value1, mixed $key2, mixed $value2): int => $value2 <=> $value1);
    }

    /**
     * @return static<K, V>
     */
    final public function ksort(): static
    {
        return $this->usortKV(static fn(mixed $key1, mixed $value1, mixed $key2): int => $key1 <=> $key2);
    }

    /**
     * @return static<K, V>
     */
    final public function ksortDesc(): static
    {
        return $this->usortKV(static fn(mixed $key1, mixed $value1, mixed $key2): int => $key2 <=> $key1);
    }

    /**
     * @param callable(V, V): int $comparator
     * @return static<K, V>
     */
    final public function usort(callable $comparator): static
    {
        return $this->usortKV(
            /**
             * @param V $value1
             * @param V $value2
             */
            static fn(mixed $key1, mixed $value1, mixed $key2, mixed $value2): int => $comparator($value1, $value2),
        );
    }

    /**
     * @param callable(K, V, K, V): int $comparator
     * @return static<K, V>
     */
    abstract public function usortKV(callable $comparator): static;

    /**
     * @return static<K, V>
     */
    abstract public function slice(int $offset, ?int $length = null): static;

    // TODO: public function keys(): Sequence
    // TODO: public function values(): Sequence
    // TODO: public function pairs(): Sequence

    /**
     * @return (K is array-key ? array<K, V>: never)
     */
    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }

    /**
     * @return ($offset is K ? bool : false)
     */
    final public function offsetExists(mixed $offset): bool
    {
        return $this->contains($offset);
    }

    /**
     * @return ($offset is K ? V : never)
     * @psalm-suppress InvalidReturnType, NoValue
     */
    final public function offsetGet(mixed $offset): mixed
    {
        return $this->getOr($offset, static fn(): never => throw new KeyIsNotDefined($offset));
    }

    final public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new \BadMethodCallException();
    }

    final public function offsetUnset(mixed $offset): never
    {
        throw new \BadMethodCallException();
    }
}
