<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Services\GroupStorageQuarantine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Laravel\TestCase;

final class GroupStorageQuarantineTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_database_failure_restores_private_file_and_rolls_back_metadata_change(): void
    {
        $path = "groups/{$this->testTenantId}/91/private/report.pdf";
        Storage::disk('local')->put($path, 'private file bytes');
        $before = DB::table('tenants')->where('id', $this->testTenantId)->value('name');

        try {
            (new GroupStorageQuarantine())->run(function ($quarantine) use ($path): never {
                $quarantine([['disk' => 'local', 'path' => $path]]);
                DB::table('tenants')->where('id', $this->testTenantId)->update(['name' => 'must roll back']);

                throw new RuntimeException('Simulated database delete/commit failure.');
            });
            self::fail('The simulated database failure should escape the coordinator.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated database delete/commit failure.', $exception->getMessage());
        }

        Storage::disk('local')->assertExists($path);
        self::assertSame('private file bytes', Storage::disk('local')->get($path));
        self::assertSame($before, DB::table('tenants')->where('id', $this->testTenantId)->value('name'));
        self::assertSame([], Storage::disk('local')->allFiles("groups/{$this->testTenantId}/91/private/.quarantine"));
    }

    public function test_database_failure_restores_media_and_thumbnail_across_local_and_public_disks(): void
    {
        $media = "groups/{$this->testTenantId}/92/media/video.mp4";
        $thumbnail = "groups/{$this->testTenantId}/92/media/video-poster.jpg";
        foreach (['local', 'public'] as $diskName) {
            Storage::disk($diskName)->put($media, "{$diskName} media bytes");
            Storage::disk($diskName)->put($thumbnail, "{$diskName} thumbnail bytes");
        }

        try {
            (new GroupStorageQuarantine())->run(function ($quarantine) use ($media, $thumbnail): never {
                $quarantine([
                    ['disk' => 'local', 'path' => $media],
                    ['disk' => 'public', 'path' => $media],
                    ['disk' => 'local', 'path' => $thumbnail],
                    ['disk' => 'public', 'path' => $thumbnail],
                ]);

                throw new RuntimeException('Simulated media metadata delete failure.');
            });
            self::fail('The simulated database failure should escape the coordinator.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated media metadata delete failure.', $exception->getMessage());
        }

        foreach (['local', 'public'] as $diskName) {
            Storage::disk($diskName)->assertExists($media);
            Storage::disk($diskName)->assertExists($thumbnail);
            self::assertSame("{$diskName} media bytes", Storage::disk($diskName)->get($media));
            self::assertSame("{$diskName} thumbnail bytes", Storage::disk($diskName)->get($thumbnail));
            self::assertSame([], Storage::disk($diskName)->allFiles("groups/{$this->testTenantId}/92/media/.quarantine"));
        }
    }

    public function test_successful_commit_removes_quarantined_bytes(): void
    {
        $path = "groups/{$this->testTenantId}/93/media/image.jpg";
        Storage::disk('local')->put($path, 'image bytes');

        $result = (new GroupStorageQuarantine())->run(function ($quarantine) use ($path): string {
            $quarantine([['disk' => 'local', 'path' => $path]]);

            return 'deleted';
        });

        self::assertSame('deleted', $result);
        Storage::disk('local')->assertMissing($path);
        self::assertSame([], Storage::disk('local')->allFiles("groups/{$this->testTenantId}/93/media/.quarantine"));
    }
}
