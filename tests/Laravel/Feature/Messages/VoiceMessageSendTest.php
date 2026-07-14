<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Messages;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression: MessageService::sendVoice sets is_voice on every voice-message send
 * (fillable + cast on the Message model) but the messages table had no
 * is_voice column — the INSERT threw "unknown column" and EVERY voice
 * message send returned 400 UPLOAD_FAILED from 2026-03-28 until the
 * 2026_06_12_120001 migration added the column. This real-DB test fails
 * loudly if the column and the write path ever drift apart again.
 */
class VoiceMessageSendTest extends TestCase
{
    use DatabaseTransactions;

    public function test_voice_message_send_persists_with_voice_fields(): void
    {
        $tenantId = $this->testTenantId;
        $sender = User::factory()->forTenant($tenantId)->create(['status' => 'active']);
        $recipient = User::factory()->forTenant($tenantId)->create(['status' => 'active']);
        TenantContext::setById($tenantId);
        $this->app->instance('tenant.id', $tenantId);

        $voiceDirectory = storage_path("app/private/message-media/{$tenantId}/voice");
        if (!is_dir($voiceDirectory)) {
            mkdir($voiceDirectory, 0775, true);
        }
        $voiceUrl = "message-media/{$tenantId}/voice/voice_test_" . uniqid() . '.webm';
        $voicePath = storage_path('app/private/' . $voiceUrl);
        file_put_contents($voicePath, 'test voice bytes');

        try {
            $result = MessageService::sendVoice(
                (int) $sender->id,
                (int) $recipient->id,
                $voiceUrl,
                7,
            );

            $this->assertNotEmpty($result, 'Voice message send failed: ' . json_encode(MessageService::getErrors()));

            $row = DB::table('messages')
                ->where('tenant_id', $tenantId)
                ->where('sender_id', $sender->id)
                ->where('audio_url', $voiceUrl)
                ->first();

            $this->assertNotNull($row, 'Voice message row not persisted');
            $this->assertSame(1, (int) $row->is_voice);
            $this->assertSame(7, (int) $row->audio_duration);
        } finally {
            @unlink($voicePath);
            TenantContext::reset();
        }
    }

    public function test_generic_service_send_rejects_raw_audio_url(): void
    {
        $tenantId = $this->testTenantId;
        $sender = User::factory()->forTenant($tenantId)->create(['status' => 'active']);
        $recipient = User::factory()->forTenant($tenantId)->create(['status' => 'active']);
        TenantContext::setById($tenantId);
        $this->app->instance('tenant.id', $tenantId);

        $craftedUrl = "/uploads/{$tenantId}/voice_messages/../../../../.env";
        try {
            $result = MessageService::send((int) $sender->id, (int) $recipient->id, [
                'body' => 'ordinary message body',
                'audio_url' => $craftedUrl,
            ]);

            $this->assertSame([], $result);
            $this->assertSame('VALIDATION_ERROR', MessageService::getErrors()[0]['code'] ?? null);
            $this->assertDatabaseMissing('messages', [
                'tenant_id' => $tenantId,
                'sender_id' => $sender->id,
                'receiver_id' => $recipient->id,
                'audio_url' => $craftedUrl,
            ]);
        } finally {
            TenantContext::reset();
        }
    }
}
