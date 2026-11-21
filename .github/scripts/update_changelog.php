<?php

declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: update_changelog.php CHANGELOG.md\n");
    exit(1);
}

$path = $argv[1];
$tag = trim((string) getenv('RELEASE_TAG'));
$publishedAt = trim((string) getenv('RELEASE_DATE'));
$notes = trim((string) getenv('RELEASE_NOTES'));

if (preg_match('/^v\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/D', $tag) !== 1) {
    fwrite(STDERR, "Release tag must use the vMAJOR.MINOR.PATCH format.\n");
    exit(1);
}

if ($notes === '') {
    fwrite(STDERR, "The published release has no notes to add to CHANGELOG.md.\n");
    exit(1);
}

$date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($publishedAt, 0, 10));

if ($date === false || $date->format('Y-m-d') !== substr($publishedAt, 0, 10)) {
    fwrite(STDERR, "Release published_at must start with an ISO date.\n");
    exit(1);
}

$changelog = file_get_contents($path);

if (! is_string($changelog)) {
    fwrite(STDERR, "Could not read {$path}.\n");
    exit(1);
}

if (preg_match('/^## \['.preg_quote($tag, '/').'\]\([^\n]+\) - \d{4}-\d{2}-\d{2}$/m', $changelog) === 1) {
    exit(0);
}

if (preg_match('~^## \[Unreleased\]\(https://github\.com/[^/]+/[^/]+/compare/(.+?)\.\.\.HEAD\)\s*$~m', $changelog, $unreleased, PREG_OFFSET_CAPTURE) !== 1) {
    fwrite(STDERR, "Could not find an Unreleased heading with its previous comparison tag.\n");
    exit(1);
}

$unreleasedHeading = $unreleased[0][0];
$unreleasedOffset = $unreleased[0][1];
$previousTag = $unreleased[1][0];
$unreleasedEnd = $unreleasedOffset + strlen($unreleasedHeading);

if (preg_match('~^## \[~m', $changelog, $nextRelease, PREG_OFFSET_CAPTURE, $unreleasedEnd) !== 1) {
    fwrite(STDERR, "Could not find the previous release heading after Unreleased.\n");
    exit(1);
}

if (preg_match('~^## \[Unreleased\]\((https://github\.com/[^/]+/[^/]+/compare/)~m', $changelog, $repository) !== 1) {
    fwrite(STDERR, "Could not read the repository comparison URL from Unreleased.\n");
    exit(1);
}

$compareBase = $repository[1];
$newUnreleased = "## [Unreleased]({$compareBase}{$tag}...HEAD)\n\n### Added\n\n### Fixed\n\n";
$newRelease = "## [{$tag}]({$compareBase}{$previousTag}...{$tag}) - {$date->format('Y-m-d')}\n\n{$notes}\n\n";
$updated = substr($changelog, 0, $unreleasedOffset)
    .$newUnreleased
    .$newRelease
    .substr($changelog, $nextRelease[0][1]);

if (file_put_contents($path, $updated) === false) {
    fwrite(STDERR, "Could not write {$path}.\n");
    exit(1);
}
