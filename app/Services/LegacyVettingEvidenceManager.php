<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;
use Illuminate\Support\Facades\Storage;

/**
 * Path-only inventory and deletion support for the retired vetting upload area.
 *
 * This service never opens, hashes, MIME-sniffs, copies, or otherwise inspects
 * evidence content. Deletion is restricted to resolved regular files beneath a
 * known `vetting/documents` root and does not follow symbolic links.
 */
class LegacyVettingEvidenceManager
{
    public const GDPR_CLEANUP_PENDING_MARKER = 'system:gdpr_file_cleanup_pending';
    public const LEGACY_REDACTION_MARKER_COLUMN = 'legacy_sensitive_metadata_redacted';

    /** @var array<string, string>|null */
    private ?array $rootOverrides;

    /** @param array<string, string>|null $rootOverrides Test/maintenance roots keyed by a non-sensitive label. */
    public function __construct(?array $rootOverrides = null)
    {
        $this->rootOverrides = $rootOverrides;
    }

    /**
     * @return list<array{root_label: string, root: string, path: string, relative_path: string, bytes: int}>
     */
    public function inventory(?string $tenantSlug = null): array
    {
        $entries = [];
        $seen = [];

        foreach ($this->roots($tenantSlug) as $label => $root) {
            $resolvedRoot = realpath($root);
            if ($resolvedRoot === false || ! is_dir($resolvedRoot) || is_link($resolvedRoot)) {
                continue;
            }

            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($resolvedRoot, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY,
                );

                /** @var SplFileInfo $file */
                foreach ($iterator as $file) {
                    $path = $file->getPathname();
                    if ($file->isLink() || ! $file->isFile()) {
                        continue;
                    }

                    $resolvedPath = realpath($path);
                    if ($resolvedPath === false || ! $this->isWithin($resolvedPath, $resolvedRoot)) {
                        continue;
                    }

                    $dedupeKey = $this->pathKey($resolvedPath);
                    if (isset($seen[$dedupeKey])) {
                        continue;
                    }
                    $seen[$dedupeKey] = true;

                    $entries[] = [
                        'root_label' => $label,
                        'root' => $resolvedRoot,
                        'path' => $resolvedPath,
                        'relative_path' => ltrim(substr($resolvedPath, strlen($resolvedRoot)), '/\\'),
                        'bytes' => max(0, (int) $file->getSize()),
                    ];
                }
            } catch (UnexpectedValueException) {
                // A missing/unreadable directory is reported as an empty root;
                // never relax containment or fall back to a broader directory.
                continue;
            }
        }

        usort($entries, static fn (array $left, array $right): int => [
            $left['root_label'], $left['relative_path'],
        ] <=> [
            $right['root_label'], $right['relative_path'],
        ]);

        return $entries;
    }

    /**
     * Delete only inventoried paths, plus a same-basename WebP derivative where
     * one exists beneath the same approved root.
     *
     * @param list<array{root_label: string, root: string, path: string, relative_path: string, bytes: int}> $entries
     * @return array{deleted: int, missing: int, refused: int, failed: int}
     */
    public function deleteInventoried(array $entries): array
    {
        $result = ['deleted' => 0, 'missing' => 0, 'refused' => 0, 'failed' => 0];
        $candidates = [];

        foreach ($entries as $entry) {
            $this->addCandidate($candidates, $entry['root'], $entry['path']);

            if (preg_match('/\.(?:jpe?g|png)$/i', $entry['path']) === 1) {
                $derivative = (string) preg_replace('/\.(?:jpe?g|png)$/i', '.webp', $entry['path']);
                if (is_file($derivative) && ! is_link($derivative)) {
                    $this->addCandidate($candidates, $entry['root'], $derivative);
                }
            }
        }

        foreach ($candidates as $candidate) {
            $root = realpath($candidate['root']);
            if ($root === false || ! is_dir($root) || is_link($root)) {
                $result['refused']++;
                continue;
            }

            if (! file_exists($candidate['path'])) {
                $result['missing']++;
                continue;
            }
            if (is_link($candidate['path']) || ! is_file($candidate['path'])) {
                $result['refused']++;
                continue;
            }

            $path = realpath($candidate['path']);
            if ($path === false || ! $this->isWithin($path, $root)) {
                $result['refused']++;
                continue;
            }

            if (@unlink($path)) {
                $result['deleted']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Determine whether a legacy database URL is a local pointer inside the
     * selected evidence roots and every possible original/WebP target is gone.
     */
    public function localPointerIsAbsent(string $url, ?string $tenantSlug = null): bool
    {
        $path = $this->localUrlPath($url);
        if ($path === null) {
            return false;
        }

        if ($tenantSlug !== null
            && ! str_starts_with($path, '/uploads/tenants/' . $tenantSlug . '/vetting/documents/')) {
            return false;
        }

        $candidates = [];
        if ($this->rootOverrides !== null) {
            foreach ($this->rootOverrides as $root) {
                $possible = $this->normaliseLexicalPath($root . '/' . basename($path));
                $candidates[$this->pathKey($possible)] = $possible;
            }
        } else {
            foreach (['httpdocs', 'public'] as $publicRoot) {
                $possible = $this->normaliseLexicalPath(base_path($publicRoot) . $path);
                $documentRoot = $this->normaliseLexicalPath(dirname($possible));
                if ($this->isWithinLexically($possible, $documentRoot)) {
                    $candidates[$this->pathKey($possible)] = $possible;
                }
            }
        }

        if ($candidates === []) {
            return false;
        }

        foreach ($candidates as $candidate) {
            if (file_exists($candidate) || is_link($candidate)) {
                return false;
            }
            if (preg_match('/\.(?:jpe?g|png)$/i', $candidate) === 1) {
                $derivative = (string) preg_replace('/\.(?:jpe?g|png)$/i', '.webp', $candidate);
                if (file_exists($derivative) || is_link($derivative)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function isExternalPointer(string $url): bool
    {
        $parts = parse_url(trim($url));

        return is_array($parts) && isset($parts['host']) && $parts['host'] !== '';
    }

    public function privateCredentialRelativePath(string $url, int $tenantId): ?string
    {
        $prefix = 'private:volunteer-credentials/' . $tenantId . '/';
        if (! str_starts_with($url, $prefix)) {
            return null;
        }

        $relative = substr($url, strlen('private:'));
        $filename = substr($url, strlen($prefix));
        if ($filename === ''
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || str_contains($filename, '..')
            || str_contains($filename, "\0")) {
            return null;
        }

        return $relative;
    }

    /** @return 'deleted'|'missing'|'refused'|'failed' */
    public function deletePrivateCredentialPointer(string $url, int $tenantId): string
    {
        if (trim($url) === '') {
            return 'missing';
        }

        $relative = $this->privateCredentialRelativePath($url, $tenantId);
        if ($relative === null) {
            return 'refused';
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($relative)) {
            return 'missing';
        }

        $rootPath = $disk->path('volunteer-credentials/' . $tenantId);
        $filePath = $disk->path($relative);
        $resolvedRoot = realpath($rootPath);
        $resolvedFile = realpath($filePath);
        if ($resolvedRoot === false
            || $resolvedFile === false
            || is_link($resolvedFile)
            || ! is_file($resolvedFile)
            || ! $this->isWithin($resolvedFile, $resolvedRoot)) {
            return 'refused';
        }

        return $disk->delete($relative) ? 'deleted' : 'failed';
    }

    /** @return array<string, string> */
    public function roots(?string $tenantSlug = null): array
    {
        if ($this->rootOverrides !== null) {
            return $this->rootOverrides;
        }

        $roots = [];
        foreach (['httpdocs', 'public'] as $publicRoot) {
            if ($tenantSlug !== null) {
                $safeSlug = trim($tenantSlug);
                if ($safeSlug !== '' && ! str_contains($safeSlug, '/') && ! str_contains($safeSlug, '\\')) {
                    $roots[$publicRoot . '_tenant'] = base_path(
                        $publicRoot . '/uploads/tenants/' . $safeSlug . '/vetting/documents'
                    );
                }
                continue;
            }

            $pattern = base_path($publicRoot . '/uploads/tenants/*/vetting/documents');
            foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $index => $path) {
                $roots[$publicRoot . '_tenant_' . $index] = $path;
            }
            $roots[$publicRoot . '_legacy'] = base_path($publicRoot . '/uploads/vetting/documents');
        }

        return $roots;
    }

    /** @param array<string, array{root: string, path: string}> $candidates */
    private function addCandidate(array &$candidates, string $root, string $path): void
    {
        $key = $this->pathKey($path);
        $candidates[$key] = ['root' => $root, 'path' => $path];
    }

    private function localUrlPath(string $url): ?string
    {
        $value = trim($url);
        if ($value === '' || $this->isExternalPointer($value) || str_contains($value, "\0")) {
            return null;
        }

        $parts = parse_url($value);
        $rawPath = is_array($parts) ? ($parts['path'] ?? '') : '';
        if (! is_string($rawPath) || $rawPath === '') {
            return null;
        }

        $decoded = rawurldecode(str_replace('\\', '/', $rawPath));
        $segments = explode('/', trim($decoded, '/'));
        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            return null;
        }

        $normal = '/' . implode('/', array_values(array_filter(
            $segments,
            static fn (string $segment): bool => $segment !== '',
        )));

        return preg_match('#^/uploads/(?:tenants/[^/]+/)?vetting/documents/[^/]+$#', $normal) === 1
            ? $normal
            : null;
    }

    private function isWithin(string $path, string $root): bool
    {
        $normalPath = rtrim($this->normaliseLexicalPath($path), '/');
        $normalRoot = rtrim($this->normaliseLexicalPath($root), '/');

        return $normalPath !== $normalRoot
            && str_starts_with($normalPath . '/', $normalRoot . '/');
    }

    private function isWithinLexically(string $path, string $root): bool
    {
        $normalPath = rtrim($path, '/');
        $normalRoot = rtrim($root, '/');

        return $normalPath !== $normalRoot
            && str_starts_with($normalPath . '/', $normalRoot . '/');
    }

    private function normaliseLexicalPath(string $path): string
    {
        $normal = str_replace('\\', '/', $path);
        if (DIRECTORY_SEPARATOR === '\\') {
            $normal = strtolower($normal);
        }

        return rtrim($normal, '/');
    }

    private function pathKey(string $path): string
    {
        return $this->normaliseLexicalPath($path);
    }
}
