<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Services\LegacyVettingEvidenceManager;
use PHPUnit\Framework\TestCase;

class LegacyVettingEvidenceManagerTest extends TestCase
{
    private string $root;
    private string $outside;

    protected function setUp(): void
    {
        parent::setUp();
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexus-vetting-evidence-' . bin2hex(random_bytes(8));
        $this->root = $base . DIRECTORY_SEPARATOR . 'vetting' . DIRECTORY_SEPARATOR . 'documents';
        $this->outside = $base . DIRECTORY_SEPARATOR . 'outside.pdf';
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        $base = dirname(dirname($this->root));
        if (is_dir($base)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($base);
        }
        parent::tearDown();
    }

    public function test_inventory_collects_path_metadata_without_content_fields(): void
    {
        file_put_contents($this->root . DIRECTORY_SEPARATOR . 'certificate.pdf', 'synthetic-test-content');
        $manager = new LegacyVettingEvidenceManager(['test_root' => $this->root]);

        $entries = $manager->inventory();

        $this->assertCount(1, $entries);
        $this->assertSame('test_root', $entries[0]['root_label']);
        $this->assertSame('certificate.pdf', $entries[0]['relative_path']);
        $this->assertArrayHasKey('bytes', $entries[0]);
        foreach (['contents', 'hash', 'mime', 'reference_number', 'notes'] as $prohibited) {
            $this->assertArrayNotHasKey($prohibited, $entries[0]);
        }
    }

    public function test_synthetic_cleanup_deletes_original_and_same_basename_webp_derivative(): void
    {
        $original = $this->root . DIRECTORY_SEPARATOR . 'certificate.jpg';
        $derivative = $this->root . DIRECTORY_SEPARATOR . 'certificate.webp';
        file_put_contents($original, 'synthetic-original');
        file_put_contents($derivative, 'synthetic-derivative');
        $manager = new LegacyVettingEvidenceManager(['test_root' => $this->root]);

        $originalEntry = array_values(array_filter(
            $manager->inventory(),
            static fn (array $entry): bool => str_ends_with($entry['path'], 'certificate.jpg'),
        ));
        $result = $manager->deleteInventoried($originalEntry);

        $this->assertSame(2, $result['deleted']);
        $this->assertFileDoesNotExist($original);
        $this->assertFileDoesNotExist($derivative);
    }

    public function test_cleanup_refuses_a_path_outside_the_approved_root(): void
    {
        file_put_contents($this->outside, 'must-survive');
        $manager = new LegacyVettingEvidenceManager(['test_root' => $this->root]);

        $result = $manager->deleteInventoried([[
            'root_label' => 'test_root',
            'root' => $this->root,
            'path' => $this->outside,
            'relative_path' => '../outside.pdf',
            'bytes' => filesize($this->outside),
        ]]);

        $this->assertSame(1, $result['refused']);
        $this->assertFileExists($this->outside);
    }

    public function test_local_pointer_is_not_clearable_while_webp_derivative_remains(): void
    {
        $manager = new LegacyVettingEvidenceManager(['test_root' => $this->root]);
        $url = '/uploads/tenants/hour-timebank/vetting/documents/certificate.jpg';
        $derivative = $this->root . DIRECTORY_SEPARATOR . 'certificate.webp';
        file_put_contents($derivative, 'synthetic-derivative');

        $this->assertFalse($manager->localPointerIsAbsent($url));
        unlink($derivative);
        $this->assertTrue($manager->localPointerIsAbsent($url));
    }
}
