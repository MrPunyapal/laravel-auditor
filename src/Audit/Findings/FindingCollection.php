<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Findings;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Evidence\Evidence;
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
            // These come from git, so they are already project-relative paths
            // and must not be filtered through the untyped-reference shape test.
            $path = self::canonical($file);

            if ($path !== '') {
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
        $candidates = 0;

        // Typed evidence is authoritative, so its path is compared directly.
        foreach ($finding->evidence as $evidence) {
            if ($evidence->reference === '') {
                continue;
            }

            $candidates++;

            $path = self::evidencePath($evidence);

            if ($path !== null && isset($paths[$path])) {
                return true;
            }
        }

        // affected_resources carry no type, so they fall back to the shape test.
        foreach ($finding->affectedResources as $resource) {
            $candidates++;

            $path = self::normalizePath($resource);

            if ($path !== null && isset($paths[$path])) {
                return true;
            }
        }

        return $candidates === 0;
    }

    /**
     * Evidence types whose reference is a file path.
     */
    private const array FILE_EVIDENCE_TYPES = ['file', 'migration', 'test'];

    /**
     * Evidence types whose reference is never a file path.
     *
     * Without this, `GET api/users.index` would be read as the file
     * `api/users.index` and a config key as `services.stripe.secret`.
     */
    private const array NON_FILE_EVIDENCE_TYPES = ['route', 'config', 'symbol', 'query', 'dependency', 'log'];

    /**
     * Resolves the path an evidence entry points at, or null when it is not a file.
     *
     * A known file type is authoritative, so its reference is used even without a
     * recognizable extension. A known non-file type is never a path. An
     * unrecognized type falls back to the extension heuristic, so a type added
     * later keeps matching instead of silently dropping out of the scope.
     */
    private static function evidencePath(Evidence $evidence): ?string
    {
        $type = mb_strtolower(trim($evidence->type));

        if (in_array($type, self::NON_FILE_EVIDENCE_TYPES, true)) {
            return null;
        }

        if (in_array($type, self::FILE_EVIDENCE_TYPES, true)) {
            return self::relativeToBase($evidence->reference);
        }

        return self::normalizePath($evidence->reference);
    }

    /**
     * The comparison form of a path: forward slashes, no leading `./`.
     *
     * Only a whole leading `./` is removed, never character-wise, because a
     * plain `ltrim($path, './')` would turn `.env` into `env`.
     */
    private static function canonical(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return $path;
    }

    /**
     * Strips the application base path so an absolute reference still compares.
     */
    private static function relativeToBase(string $reference): ?string
    {
        $path = self::canonical($reference);

        if ($path === '') {
            return null;
        }

        $base = str_replace('\\', '/', rtrim(base_path(), '/\\'));

        if ($base !== '' && str_starts_with($path, $base.'/')) {
            $path = substr($path, strlen($base) + 1);
        }

        return self::isSafeRelativePath($path) ? $path : null;
    }

    /**
     * Normalizes a reference that is a file path, or null when it is not one.
     *
     * Used for `affected_resources`, which carry no evidence type. Routes
     * (`GET api/users`), config keys (`services.stripe.secret`),
     * symbols (`App\Models\User@save`), queries, and package names all carry
     * dots or slashes but are not files, so only extension-bearing relative
     * paths are treated as files.
     */
    private static function normalizePath(string $reference): ?string
    {
        $path = self::canonical($reference);

        return self::looksLikeFilePath($path) ? $path : null;
    }

    /**
     * Rejects absolute, parent, and URL references.
     */
    private static function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:#', $path) === 1) {
            return false;
        }

        return ! str_contains($path, '://') && ! in_array('..', explode('/', $path), true);
    }

    /**
     * Whether an untyped reference is shaped like a source file path.
     */
    private static function looksLikeFilePath(string $path): bool
    {
        if (! self::isSafeRelativePath($path)) {
            return false;
        }

        // Dotfiles carry no useful extension (`.env`, `.env.example`), and a
        // config key never starts with a dot segment at the root of a path.
        if (str_starts_with(basename($path), '.') && preg_match('#^\.[A-Za-z0-9_.\-]+$#', basename($path)) === 1) {
            return true;
        }

        return preg_match('#^[A-Za-z0-9_./\- ]+\.(php|blade\.php|js|ts|tsx|jsx|vue|css|scss|json|ya?ml|md|env|sql|xml|twig)$#i', $path) === 1;
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
