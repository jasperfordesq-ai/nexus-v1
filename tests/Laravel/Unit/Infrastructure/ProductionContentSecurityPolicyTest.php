<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

class ProductionContentSecurityPolicyTest extends TestCase
{
    private const POLICY_VARIABLE = '$nexus_spa_content_security_policy';

    /** @var list<string> */
    private const CONFIG_FILES = [
        'react-frontend/nginx.conf',
        'react-frontend/nginx.bluegreen.conf',
    ];

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 4);
    }

    public function test_every_spa_response_uses_one_complete_policy(): void
    {
        $canonicalPolicy = null;

        foreach (self::CONFIG_FILES as $file) {
            $source = (string) file_get_contents($this->root . DIRECTORY_SEPARATOR . $file);
            $policy = $this->extractCanonicalPolicy($source, $file);
            $directives = $this->parseCsp($policy);

            if ($canonicalPolicy === null) {
                $canonicalPolicy = $policy;
            } else {
                self::assertSame($canonicalPolicy, $policy, "{$file} must match the canonical SPA CSP");
            }

            $requiredSources = [
                'script-src' => ["'self'", 'https://maps.googleapis.com', 'https://maps.gstatic.com'],
                'img-src' => [
                    "'self'",
                    'https://tile.openstreetmap.org',
                    'https://*.tile.openstreetmap.org',
                    'https://api.maptiler.com',
                    'https://api.os.uk',
                    'https://api.project-nexus.ie',
                ],
                'connect-src' => [
                    "'self'",
                    'https://api.project-nexus.ie',
                    'https://nominatim.openstreetmap.org',
                ],
                'frame-src' => [
                    "'self'",
                    'https://api.project-nexus.ie',
                    'https://*.google.com',
                    'https://w.soundcloud.com',
                    'https://player.twitch.tv',
                    'https://clips.twitch.tv',
                ],
                'media-src' => ["'self'", 'https:'],
                'worker-src' => ["'self'", 'blob:'],
                'frame-ancestors' => ["'self'"],
                'report-uri' => ['https://api.project-nexus.ie/api/csp-report'],
                'report-to' => ['nexus-csp'],
            ];
            foreach ($requiredSources as $directive => $sources) {
                self::assertArrayHasKey($directive, $directives, "{$file} is missing {$directive}");
                foreach ($sources as $requiredSource) {
                    self::assertContains(
                        $requiredSource,
                        $directives[$directive],
                        "{$file} {$directive} must allow {$requiredSource}",
                    );
                }
            }

            self::assertNotContains("'unsafe-inline'", $directives['script-src']);
            self::assertNotContains("'unsafe-eval'", $directives['script-src']);
            self::assertNotContains('https:', $directives['script-src']);
            self::assertNotContains('https:', $directives['connect-src']);

            $this->assertEveryEmittedPolicyIsIntentional($source, $file);
        }
    }

    private function extractCanonicalPolicy(string $source, string $file): string
    {
        $matched = preg_match(
            '/map\s+\$host\s+\$nexus_spa_content_security_policy\s*\{\s*default\s+"([^"]+)";\s*\}/s',
            $source,
            $matches,
        );

        self::assertSame(1, $matched, "{$file} must define the canonical SPA CSP map");

        return (string) ($matches[1] ?? '');
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseCsp(string $policy): array
    {
        $directives = [];
        foreach (explode(';', $policy) as $segment) {
            $tokens = preg_split('/\s+/', trim($segment)) ?: [];
            if ($tokens === []) {
                continue;
            }

            $name = array_shift($tokens);
            if (is_string($name) && $name !== '') {
                $directives[$name] = array_values($tokens);
            }
        }

        return $directives;
    }

    private function assertEveryEmittedPolicyIsIntentional(string $source, string $file): void
    {
        $lines = preg_split('/\R/', $source) ?: [];
        $spaPolicyCount = 0;
        $reportingEndpointCount = 0;
        $resetPolicyCount = 0;

        foreach ($lines as $line) {
            if (trim($line) === 'add_header Reporting-Endpoints \'nexus-csp="https://api.project-nexus.ie/api/csp-report"\' always;') {
                $reportingEndpointCount++;
                continue;
            }

            if (! str_contains($line, 'add_header Content-Security-Policy')) {
                continue;
            }

            $trimmed = trim($line);
            if ($trimmed === 'add_header Content-Security-Policy ' . self::POLICY_VARIABLE . ' always;') {
                $spaPolicyCount++;
                continue;
            }

            if ($trimmed === 'add_header Content-Security-Policy "default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\';" always;') {
                $resetPolicyCount++;
                continue;
            }

            // Non-HTML/static metadata endpoints intentionally deny every load.
            if (str_starts_with($trimmed, 'add_header Content-Security-Policy "default-src \'none\';')) {
                continue;
            }

            self::fail("{$file} emits a non-canonical CSP: {$trimmed}");
        }

        self::assertGreaterThan(0, $spaPolicyCount, "{$file} must emit the canonical SPA CSP");
        self::assertSame(
            $spaPolicyCount,
            $reportingEndpointCount,
            "{$file} must emit a reporting endpoint alongside every canonical SPA CSP",
        );
        self::assertSame(1, $resetPolicyCount, "{$file} must have one route-scoped service-worker reset policy");
    }
}
