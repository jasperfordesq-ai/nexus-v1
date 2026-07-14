<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Migrations;

use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Laravel\TestCase;

final class PrivateMessageMediaMigrationTest extends TestCase
{
    public function test_maps_all_approved_production_legacy_media_roots(): void
    {
        $migration = require dirname(__DIR__, 4) . '/database/migrations/2026_07_14_000200_move_message_media_to_private_storage.php';

        $attachment = $this->invoke($migration, 'legacyAttachmentSource', (object) [
            'tenant_id' => $this->testTenantId,
            'file_path' => '/var/www/html/src/Services/../../httpdocs/uploads/messages/msg_238_example.pdf',
            'file_url' => null,
        ]);
        self::assertSame([
            'msg_238_example.pdf',
            base_path('httpdocs/uploads/messages/msg_238_example.pdf'),
        ], $attachment);

        $slug = (string) DB::table('tenants')->where('id', $this->testTenantId)->value('slug');
        self::assertNotSame('', $slug);
        $voice = $this->invoke($migration, 'legacyVoiceSource', (object) [
            'tenant_id' => $this->testTenantId,
            'audio_url' => "/uploads/tenants/{$slug}/voice_messages/voice_example.webm",
        ]);
        self::assertSame([
            'voice_example.webm',
            base_path("httpdocs/uploads/tenants/{$slug}/voice_messages/voice_example.webm"),
        ], $voice);
    }

    public function test_rejects_unapproved_or_traversing_legacy_media_paths(): void
    {
        $migration = require dirname(__DIR__, 4) . '/database/migrations/2026_07_14_000200_move_message_media_to_private_storage.php';

        self::assertNull($this->invoke($migration, 'legacyAttachmentSource', (object) [
            'tenant_id' => $this->testTenantId,
            'file_path' => '/var/www/html/httpdocs/uploads/insurance/secret.pdf',
            'file_url' => null,
        ]));
        self::assertNull($this->invoke($migration, 'legacyVoiceSource', (object) [
            'tenant_id' => $this->testTenantId,
            'audio_url' => "/uploads/{$this->testTenantId}/voice_messages/../secret.webm",
        ]));
    }

    private function invoke(object $migration, string $method, object $row): mixed
    {
        $reflection = new ReflectionMethod($migration, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($migration, $row);
    }
}
