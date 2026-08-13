<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Evidence;

use JsonSerializable;

/**
 * A single piece of verifiable context that supports an audit finding.
 *
 * Evidence should reference concrete project facts rather than model guesses:
 * a file path and line range, a symbol, a route, a migration, a query,
 * a configuration key, a dependency, or another verifiable reference.
 */
final class Evidence implements JsonSerializable
{
    /**
     * @param  string  $type  Evidence type, one of: file, symbol, route, migration, query, config, dependency, test, log, other.
     * @param  string  $reference  The concrete reference (path, symbol, route URI, config key, ...).
     * @param  int|null  $line  The starting line, when the reference is a file.
     * @param  int|null  $endLine  The ending line, when the reference is a file range.
     * @param  string|null  $detail  Optional explanatory detail or excerpt.
     * @param  array<string, mixed>  $metadata  Additional structured context.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $reference,
        public readonly ?int $line = null,
        public readonly ?int $endLine = null,
        public readonly ?string $detail = null,
        public readonly array $metadata = [],
    ) {}

    public static function file(string $path, ?int $line = null, ?int $endLine = null, ?string $detail = null): self
    {
        return new self('file', $path, $line, $endLine, $detail);
    }

    public static function symbol(string $symbol, ?string $detail = null): self
    {
        return new self('symbol', $symbol, null, null, $detail);
    }

    public static function route(string $method, string $uri, ?string $detail = null): self
    {
        return new self('route', "{$method} {$uri}", null, null, $detail);
    }

    public static function config(string $key, ?string $detail = null): self
    {
        return new self('config', $key, null, null, $detail);
    }

    public static function dependency(string $package, ?string $version = null, ?string $detail = null): self
    {
        return new self('dependency', $package, null, null, $detail ?? $version);
    }

    public static function migration(string $path, ?string $detail = null): self
    {
        return new self('migration', $path, null, null, $detail);
    }

    /**
     * @return array{type: string, reference: string, line: int|null, end_line: int|null, detail: string|null, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'reference' => $this->reference,
            'line' => $this->line,
            'end_line' => $this->endLine,
            'detail' => $this->detail,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) ($data['type'] ?? 'other'),
            reference: (string) ($data['reference'] ?? ''),
            line: isset($data['line']) ? (int) $data['line'] : null,
            endLine: isset($data['end_line']) ? (int) $data['end_line'] : null,
            detail: isset($data['detail']) ? (string) $data['detail'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /**
     * @return array{type: string, reference: string, line: int|null, end_line: int|null, detail: string|null, metadata: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
