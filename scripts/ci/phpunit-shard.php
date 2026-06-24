<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * phpunit-shard.php — Deterministically partition the PHPUnit test files
 * across CI matrix shards so the ~12.5k-test Laravel suite runs in parallel.
 *
 * The Laravel + LaravelMigrated suites together cover every *Test.php under
 * tests/Laravel (the Laravel suite excludes tests/Laravel/Migrated, which the
 * LaravelMigrated suite then runs — their union is the whole directory). This
 * script lists those files and splits them into `total` balanced buckets with
 * a longest-processing-time greedy bin-pack keyed on file size (a reasonable
 * proxy for test count / runtime), then prints the files for one shard.
 *
 * Files are assigned by WHOLE file, so any intra-class @depends stays inside a
 * single shard (the suite currently declares none). Output is newline-separated
 * repo-relative paths, suitable for:
 *   vendor/bin/phpunit $(php scripts/ci/phpunit-shard.php <shard> <total>)
 *
 * Usage:  php scripts/ci/phpunit-shard.php <shardIndex 1..total> <total>
 * Exit 0 with the file list on success; exit 2 on bad arguments / missing dir.
 */

$shard = (int) ($argv[1] ?? 0);
$total = (int) ($argv[2] ?? 0);

if ($total < 1 || $shard < 1 || $shard > $total) {
    fwrite(STDERR, "usage: php scripts/ci/phpunit-shard.php <shardIndex 1..total> <total>\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
$testDir = $root . '/tests/Laravel';

if (!is_dir($testDir)) {
    fwrite(STDERR, "test directory not found: {$testDir}\n");
    exit(2);
}

$files = [];
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($iter as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
        $files[] = $file->getPathname();
    }
}

// Deterministic order: largest file first, ties broken by path, so the
// bin-pack produces the same buckets on every runner for a given commit.
usort($files, static function (string $a, string $b): int {
    return (filesize($b) <=> filesize($a)) ?: strcmp($a, $b);
});

// Greedy LPT bin-pack into `total` buckets keyed on accumulated file size.
$bucketSize = array_fill(1, $total, 0);
$bucketFiles = array_fill(1, $total, []);
foreach ($files as $path) {
    $min = 1;
    for ($b = 2; $b <= $total; $b++) {
        if ($bucketSize[$b] < $bucketSize[$min]) {
            $min = $b;
        }
    }
    $bucketSize[$min] += max(1, (int) filesize($path));
    $bucketFiles[$min][] = $path;
}

$out = [];
foreach ($bucketFiles[$shard] as $path) {
    $out[] = substr($path, strlen($root) + 1);
}
sort($out, SORT_STRING);

echo implode("\n", $out), "\n";
