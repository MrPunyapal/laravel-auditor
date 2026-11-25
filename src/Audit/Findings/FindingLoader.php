<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Findings;

use JsonException;
use RuntimeException;
use Throwable;

/**
 * Loads a FindingCollection from a JSON findings file.
 */
final class FindingLoader
{
    public function load(string $path): FindingCollection
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Findings file [{$path}] does not exist.");
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException("Findings file [{$path}] could not be read.");
        }

        try {
            $shape = json_decode($contents, flags: JSON_THROW_ON_ERROR);
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid findings JSON: '.$e->getMessage(), previous: $e);
        }

        $shapeList = is_object($shape) ? ($shape->findings ?? null) : $shape;

        if (! is_array($shapeList) || ! is_array($data)) {
            throw new RuntimeException('Findings file must contain a JSON array of findings.');
        }

        $list = is_array($data['findings'] ?? null) ? $data['findings'] : $data;

        $findings = [];

        foreach (array_values($list) as $index => $item) {
            if (! is_object($shapeList[$index]) || ! is_array($item)) {
                throw new RuntimeException("Findings file [{$path}] item [{$index}] must be an object.");
            }

            try {
                $findings[] = Finding::fromArray($item);
            } catch (Throwable $e) {
                throw new RuntimeException("Findings file [{$path}] item [{$index}] is invalid: {$e->getMessage()}", previous: $e);
            }
        }

        return new FindingCollection(...$findings);
    }
}
