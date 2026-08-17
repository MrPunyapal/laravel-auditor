<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Findings;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use LaravelAuditor\Audit\Enums\Severity;
use Traversable;

/**
 * A sortable collection of findings.
 *
 * Supports ArrayAccess, iteration, and counting. The `sorted()` and
 * `atLeast()` methods return new instances; `add()` mutates in place.
 *
 * @implements ArrayAccess<int, Finding>
 * @implements IteratorAggregate<int, Finding>
 */
final class FindingCollection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @var array<int, Finding>
     */
    private array $items;

    public function __construct(Finding ...$items)
    {
        $this->items = array_values($items);
    }

    /**
     * @param  iterable<Finding>  $items
     */
    public static function fromIterable(iterable $items): self
    {
        return new self(...iterator_to_array($items, preserve_keys: false));
    }

    public function add(Finding $finding): self
    {
        $this->items[] = $finding;

        return $this;
    }

    /**
     * @return list<Finding>
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
     * Findings sorted by severity (highest first), then confidence (highest first).
     */
    public function sorted(): self
    {
        $items = $this->items;

        usort($items, static function (Finding $a, Finding $b): int {
            return $b->severity->weight() <=> $a->severity->weight()
                ?: $b->confidence->weight() <=> $a->confidence->weight()
                ?: strcmp($a->id, $b->id);
        });

        return new self(...$items);
    }

    /**
     * Findings with the given severity or above.
     */
    public function atLeast(Severity $severity): self
    {
        return new self(...array_values(array_filter(
            $this->items,
            static fn (Finding $finding): bool => $finding->severity->weight() >= $severity->weight(),
        )));
    }

    /**
     * @return array<string, int>
     */
    public function countsBySeverity(): array
    {
        $counts = array_fill_keys(
            array_map(static fn (Severity $s): string => $s->value, Severity::cases()),
            0,
        );

        foreach ($this->items as $finding) {
            $counts[$finding->severity->value]++;
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function countsByDomain(): array
    {
        $counts = [];

        foreach ($this->items as $finding) {
            $counts[$finding->domain->value] = ($counts[$finding->domain->value] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_values(array_map(static fn (Finding $finding): array => $finding->toArray(), $this->items));
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

    public function offsetGet(mixed $offset): Finding
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
