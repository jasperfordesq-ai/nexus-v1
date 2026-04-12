<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * audit-static-services.php
 *
 * Scans app/Services/ (and src/Services/ if present) for services that still
 * use static methods. Outputs a sorted list:
 *
 *   ServiceName -> N static methods
 *
 * Usage:
 *   php scripts/audit-static-services.php
 *
 * Part of the TD9 service layer DI refactor. See docs/SERVICE_LAYER.md for the
 * conversion plan and pattern.
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$dirs = [
    $projectRoot . '/app/Services',
    $projectRoot . '/src/Services',
];

$results = [];
$totalStaticMethods = 0;

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($rii as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $contents = @file_get_contents($path);
        if ($contents === false) {
            continue;
        }

        // Count `public static function` — the DI-hostile form.
        // Constants, private static helpers, and `static function` without
        // `public` are NOT counted (private statics are acceptable; only
        // public statics force callers into static dispatch).
        $staticCount = preg_match_all('/\bpublic\s+static\s+function\s+\w+/i', $contents, $m);
        if ($staticCount > 0) {
            $name = $file->getBasename('.php');
            // Guard against duplicate hits (same file in both dirs)
            $results[$name] = max($results[$name] ?? 0, $staticCount);
        }
    }
}

arsort($results);
$totalStaticMethods = array_sum($results);
$count = count($results);

$header = sprintf(
    "%d services still have public static methods (%d total static method declarations).\n"
  . "See docs/SERVICE_LAYER.md for conversion plan.\n",
    $count,
    $totalStaticMethods
);

echo $header;
echo str_repeat('=', 78) . "\n";

if ($count === 0) {
    echo "All services are fully instance-based. Nothing to do.\n";
    exit(0);
}

$nameWidth = max(array_map('strlen', array_keys($results))) + 2;
printf("%-{$nameWidth}s %s\n", 'Service', 'Public static methods');
echo str_repeat('-', 78) . "\n";
foreach ($results as $name => $n) {
    printf("%-{$nameWidth}s %d\n", $name, $n);
}

echo "\n";
echo "To pick the next service to convert, see the 'How to pick' section of\n";
echo "docs/SERVICE_LAYER.md. Prefer services with 5-30 callers.\n";
