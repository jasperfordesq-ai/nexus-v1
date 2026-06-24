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
 * script lists those files and assigns each WHOLE file to a shard, then prints
 * the files for one shard.
 *
 * Partitioning is keyed on the file's repo-relative PATH (round-robin over the
 * sorted path list), NOT on file size.
 *
 *   Why not file size? The previous LPT bin-pack keyed buckets on filesize as a
 *   runtime proxy. But filesize changes whenever ANY test file is edited — e.g.
 *   quarantining one flaky test by adding a markTestSkipped() line. That shifted
 *   the bin-pack and silently moved UNRELATED test files between shards, which,
 *   on a suite with cross-class data-isolation debt, exposed a brand-new set of
 *   order-dependent failures on every commit. Quarantine-to-green could never
 *   converge: each fix reshuffled the deck. Pinning files to shards by path
 *   keeps every file in the same shard across content edits, so the failing set
 *   is stable and quarantines actually stick.
 *
 * Files are assigned by WHOLE file, so any intra-class @depends stays inside a
 * single shard (the suite currently declares none). Output is newline-separated
 * repo-relative paths, suitable for:
 *   vendor/bin/phpunit $(php scripts/ci/phpunit-shard.php <shard> <total>)
 *
 * Trade-off: round-robin balances by file COUNT, not runtime. Because path order
 * is uncorrelated with file size, sizes spread roughly evenly across shards in
 * practice; we accept slightly looser balance in exchange for a partition that
 * is stable under content edits (the property that makes CI converge).
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
        // Store the repo-relative path; the partition key must not depend on the
        // absolute checkout location, only on the path within the repo.
        $files[] = substr($file->getPathname(), strlen($root) + 1);
    }
}

// Deterministic, edit-stable order: sort by repo-relative path. Sorting first
// makes the round-robin assignment below a pure function of the file SET, so
// every runner produces the same buckets for a given commit, and editing a
// file's contents never moves it (its path, hence its rank, is unchanged).
sort($files, SORT_STRING);

// Round-robin: file at sorted index $i goes to shard ($i % $total) + 1.
$out = [];
foreach ($files as $i => $relPath) {
    if (($i % $total) + 1 === $shard) {
        $out[] = $relPath;
    }
}

sort($out, SORT_STRING);
echo implode("\n", $out), "\n";
