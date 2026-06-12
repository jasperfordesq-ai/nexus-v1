<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Messages;

use App\Core\TenantContext;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression: MessageService::send sets is_voice on every voice-message send
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
        $tenantId = (int) DB::table('tenants')->where('is_active', 1)->orderBy('id')->value('id');
        $users = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('id')
            ->limit(2)
            ->pluck('id')
            ->all();
        if (count($users) < 2) {
            $this->markTestSkipped('Test DB lacks two active users');
        }
        TenantContext::setById($tenantId);
        $this->app->instance('tenant.id', $tenantId);

        try {
            $result = MessageService::send((int) $users[0], (int) $users[1], [
                'body' => '',
                'is_voice' => true,
                'audio_url' => '/uploads/voice/test-clip.webm',
                'audio_duration' => 7,
            ]);

            $this->assertNotEmpty($result, 'Voice message send failed: ' . json_encode(MessageService::getErrors()));

            $row = DB::table('messages')
                ->where('tenant_id', $tenantId)
                ->where('sender_id', $users[0])
                ->where('audio_url', '/uploads/voice/test-clip.webm')
                ->first();

            $this->assertNotNull($row, 'Voice message row not persisted');
            $this->assertSame(1, (int) $row->is_voice);
            $this->assertSame(7, (int) $row->audio_duration);
        } finally {
            TenantContext::reset();
        }
    }
}
