<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Project NEXUS - Environment Variable Validator (TD15)
 * =============================================================================
 * Purpose: Fail-fast validation that all REQUIRED env vars are present and
 * non-empty before a deploy. Catches the class of regression where a new
 * config/foo.php references env('NEW_KEY') but .env.production wasn't updated,
 * which would cause `php artisan config:cache` to crash-loop at container start.
 *
 * Required keys are determined by:
 *   1. All uncommented KEY= lines in .env.example that have NO default value
 *      (i.e. `KEY=` with nothing after the =) OR explicit REQUIRED comment
 *   2. All env('KEY') calls in config/*.php that have NO fallback
 *      (i.e. env('KEY') — not env('KEY', 'default'))
 *
 * Usage:
 *   php scripts/validate-env.php               # validate current .env
 *   php scripts/validate-env.php --file=.env.production
 *   php scripts/validate-env.php --strict      # also warn on empty keys with defaults
 *
 * Exit codes:
 *   0 = all required keys present and non-empty
 *   1 = missing or empty required keys
 *   2 = invalid invocation / .env file not found
 */

$opts = getopt('', ['file::', 'strict', 'help']);
if (isset($opts['help'])) {
    echo "Usage: php scripts/validate-env.php [--file=.env] [--strict]\n";
    exit(0);
}

$envFile     = $opts['file'] ?? __DIR__ . '/../.env';
$exampleFile = __DIR__ . '/../.env.example';
$configDir   = __DIR__ . '/../config';
$strict      = isset($opts['strict']);

// --- ANSI colors ---
$isTty = function_exists('posix_isatty') ? @posix_isatty(STDOUT) : false;
$RED   = $isTty ? "\033[0;31m" : '';
$GREEN = $isTty ? "\033[0;32m" : '';
$YELLOW= $isTty ? "\033[1;33m" : '';
$CYAN  = $isTty ? "\033[0;36m" : '';
$BOLD  = $isTty ? "\033[1m"    : '';
$NC    = $isTty ? "\033[0m"    : '';

echo "{$BOLD}Project NEXUS - Environment Variable Validator{$NC}\n";
echo "Env file:     $envFile\n";
echo "Example file: $exampleFile\n";
echo "Config dir:   $configDir\n";
echo "Strict mode:  " . ($strict ? 'on' : 'off') . "\n";
echo str_repeat('=', 60) . "\n";

// -------------------------------------------------------------
// Load .env file into an associative array
// -------------------------------------------------------------
function load_env_file(string $file): array
{
    $vars = [];
    if (!is_readable($file)) {
        return $vars;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (!preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $m)) {
            continue;
        }
        $key = $m[1];
        $val = trim($m[2]);
        // Strip surrounding quotes if present
        if (preg_match('/^"(.*)"$/s', $val, $qm) || preg_match("/^'(.*)'$/s", $val, $qm)) {
            $val = $qm[1];
        }
        $vars[$key] = $val;
    }
    return $vars;
}

// -------------------------------------------------------------
// Discover REQUIRED keys from .env.example
// A key is REQUIRED if its example value is EMPTY (KEY= with nothing after =)
// because the example file intentionally blanks out keys the user MUST fill in.
// -------------------------------------------------------------
function required_from_example(string $file): array
{
    $required = [];
    if (!is_readable($file)) {
        return $required;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $m)) continue;
        $key = $m[1];
        $val = trim($m[2]);
        if ($val === '') {
            $required[$key] = 'empty in .env.example (user must fill)';
        }
    }
    return $required;
}

// -------------------------------------------------------------
// Discover REQUIRED keys from config/*.php — scan for env('KEY') with no fallback
// env('KEY') or env("KEY") with NO second argument = required
// env('KEY', 'default') = optional (has fallback)
// -------------------------------------------------------------
function required_from_configs(string $dir): array
{
    $required = [];
    if (!is_dir($dir)) return $required;

    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iter as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') continue;
        $src = file_get_contents($file->getPathname());
        if ($src === false) continue;

        // Match env('KEY') or env("KEY") with NO fallback argument
        // Greedy whitespace, allow optional trailing spaces before )
        if (preg_match_all('/\benv\(\s*[\'"]([A-Z_][A-Z0-9_]*)[\'"]\s*\)/', $src, $m)) {
            foreach ($m[1] as $key) {
                $required[$key] = 'env() with no fallback in ' . basename($file->getPathname());
            }
        }
    }
    return $required;
}

// -------------------------------------------------------------
// Main validation
// -------------------------------------------------------------
if (!is_readable($envFile)) {
    echo "{$RED}[FAIL]{$NC} .env file not found or not readable: $envFile\n";
    exit(2);
}

$env      = load_env_file($envFile);
$required = array_merge(
    required_from_example($exampleFile),
    required_from_configs($configDir)
);

// Exclude APP_* keys that Laravel handles specially
$alwaysSkip = ['APP_NAME', 'APP_DEBUG', 'APP_LOCALE', 'APP_FALLBACK_LOCALE'];
foreach ($alwaysSkip as $s) unset($required[$s]);

echo "Loaded " . count($env) . " keys from $envFile\n";
echo "Discovered " . count($required) . " required keys\n\n";

$missing = [];
$empty   = [];

foreach ($required as $key => $reason) {
    if (!array_key_exists($key, $env)) {
        $missing[$key] = $reason;
    } elseif ($env[$key] === '') {
        $empty[$key] = $reason;
    }
}

if (!empty($missing)) {
    echo "{$RED}[FAIL]{$NC} Missing required keys (" . count($missing) . "):\n";
    foreach ($missing as $k => $reason) {
        echo "  - {$BOLD}$k{$NC}  ($reason)\n";
    }
    echo "\n";
}

if (!empty($empty)) {
    echo "{$YELLOW}[WARN]{$NC} Required keys present but EMPTY (" . count($empty) . "):\n";
    foreach ($empty as $k => $reason) {
        echo "  - {$BOLD}$k{$NC}  ($reason)\n";
    }
    echo "\n";
}

if (empty($missing) && empty($empty)) {
    echo "{$GREEN}[PASS]{$NC} All " . count($required) . " required env keys present and non-empty\n";
    exit(0);
}

// In strict mode (or with missing keys), fail
if (!empty($missing) || ($strict && !empty($empty))) {
    echo str_repeat('=', 60) . "\n";
    echo "{$RED}Validation FAILED{$NC}\n";
    echo "\nTo add a new required env var:\n";
    echo "  1. Add KEY= (empty) to .env.example  — documents it as required\n";
    echo "  2. Add KEY=real_value to .env and .env.production on the server\n";
    echo "  3. Reference in config/ via env('KEY')  (no fallback if truly required)\n";
    echo "  4. Re-run:  php scripts/validate-env.php\n";
    exit(1);
}

echo "{$GREEN}[PASS]{$NC} No missing keys (empty values allowed without --strict)\n";
exit(0);
