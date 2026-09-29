<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Findings;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use LaravelAuditor\Audit\Enums\AuditDomain;
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
     * Findings that reference at least one of the given files.
     *
     * Both `evidence` references and `affected_resources` are considered, since
     * a finding may point at a file in either place. A finding that references no
     * file at all is kept: it cannot be proven unrelated to the change, and
     * silently dropping it would hide a real problem.
     *
     * @param  list<string>  $files
     */
    public function touching(array $files): self
    {
        if ($files === []) {
            return new self;
        }

        $normalized = [];

        foreach ($files as $file) {
            $path = self::normalizePath($file);

            if ($path !== null) {
                $normalized[$path] = true;
            }
        }

        if ($normalized === []) {
            return new self;
        }

        return new self(...array_values(array_filter(
            $this->items,
            static fn (Finding $finding): bool => self::referencesAny($finding, $normalized),
        )));
    }

    /**
     * @param  array<string, true>  $paths
     */
    private static function referencesAny(Finding $finding, array $paths): bool
    {
        $references = [];

        foreach ($finding->evidence as $evidence) {
            if ($evidence->reference !== '') {
                $references[] = $evidence->reference;
            }
        }

        foreach ($finding->affectedResources as $resource) {
            $references[] = $resource;
        }

        if ($references === []) {
            return true;
        }

        foreach ($references as $reference) {
            $path = self::normalizePath($reference);

            if ($path !== null && isset($paths[$path])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalizes a reference that is a file path, or null when it is not one.
     *
     * Routes (`GET api/users`), config keys (`services.stripe.secret`),
     * symbols (`App\Models\User@save`), queries, and package names all carry
     * dots or slashes but are not files, so only extension-bearing relative
     * paths are treated as files.
     */
    private static function normalizePath(string $reference): ?string
    {
        $path = str_replace('\\', '/', trim($reference));

        if (preg_match('#^[A-Za-z0-9_./\- ]+\.(php|blade\.php|js|ts|tsx|jsx|vue|css|scss|json|ya?ml|md|env|sql|xml|twig)$#i', $path) !== 1) {
            return null;
        }

        $path = ltrim($path, './');

        return $path === '' ? null : $path;
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
        $counts = array_fill_keys(
            array_map(static fn (AuditDomain $d): string => $d->value, AuditDomain::cases()),
            0,
        );

        foreach ($this->items as $finding) {
            $counts[$finding->domain->value]++;
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
