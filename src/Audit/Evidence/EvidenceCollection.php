<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Evidence;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements ArrayAccess<int, Evidence>
 * @implements IteratorAggregate<int, Evidence>
 */
final class EvidenceCollection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @var array<int, Evidence>
     */
    private array $items;

    public function __construct(Evidence ...$items)
    {
        $this->items = array_values($items);
    }

    /**
     * @param  iterable<Evidence>  $items
     */
    public static function fromIterable(iterable $items): self
    {
        return new self(...iterator_to_array($items, preserve_keys: false));
    }

    public function add(Evidence $evidence): self
    {
        $this->items[] = $evidence;

        return $this;
    }

    /**
     * @return list<Evidence>
     */
    public function all(): array
    {
        return array_values($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_values(array_map(fn (Evidence $evidence): array => $evidence->toArray(), $this->items));
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        yield from $this->items;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): Evidence
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[(int) $offset] = $value;
            $this->items = array_values($this->items);
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
