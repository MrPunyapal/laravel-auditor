<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Findings;

use JsonException;
use RuntimeException;

/**
 * Loads a FindingCollection from a JSON findings file.
 */
final class FindingLoader
{
    public function load(string $path): FindingCollection
    {
        if (! file_exists($path)) {
            throw new RuntimeException("Findings file [{$path}] does not exist.");
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid findings JSON: '.$e->getMessage(), previous: $e);
        }

        if (! is_array($data)) {
            throw new RuntimeException('Findings file must contain a JSON array of findings.');
        }

        $list = is_array($data['findings'] ?? null) ? $data['findings'] : $data;

        return FindingCollection::fromIterable(array_map(
            static fn (array $item): Finding => Finding::fromArray($item),
            $list,
        ));
    }
}
