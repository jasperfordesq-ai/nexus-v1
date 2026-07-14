<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Core\TenantContext;
use App\Models\Concerns\HasTenantScope;
use App\Models\MessageAttachment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

class MessageAttachmentTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99764;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'Test Tenant 99764',
                'slug'              => 'test-99764',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );
        TenantContext::setById(self::TENANT_ID);
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    /**
     * Insert a bare messages row to satisfy the message_attachments FK.
     */
    private function seedMessage(): int
    {
        // Seed a minimal user for sender/receiver FK
        $userId = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'Test User',
            'email'       => 'attach-user-' . uniqid() . '@example.test',
            'is_active'   => 1,
            'is_approved' => 1,
            'status'      => 'active',
            'created_at'  => now(),
        ]);

        return DB::table('messages')->insertGetId([
            'tenant_id'      => self::TENANT_ID,
            'sender_id'      => $userId,
            'receiver_id'    => $userId,
            'subject'        => 'Test',
            'body'           => 'Test body',
            'created_at'     => now(),
        ]);
    }

    private function makeAttachment(array $overrides = []): MessageAttachment
    {
        $msgId = $this->seedMessage();

        $data = array_merge([
            'message_id' => $msgId,
            'file_url'   => 'https://cdn.example.com/file.pdf',
            'file_path'  => 'uploads/file.pdf',
            'file_name'  => 'file.pdf',
            'file_type'  => 'file',
            'mime_type'  => 'application/pdf',
            'file_size'  => 1024,
        ], $overrides);

        DB::table('message_attachments')->insert(array_merge($data, ['tenant_id' => self::TENANT_ID]));

        return MessageAttachment::where('message_id', $msgId)->firstOrFail();
    }

    // -------------------------------------------------------------------
    // Accessor: getUrlAttribute
    // -------------------------------------------------------------------

    public function test_url_accessor_returns_authenticated_delivery_url(): void
    {
        $attachment = $this->makeAttachment(['file_url' => 'https://cdn.example.com/photo.jpg']);
        $this->assertSame(
            "/api/v2/messages/{$attachment->message_id}/attachments/{$attachment->id}",
            $attachment->url,
        );
    }

    public function test_url_accessor_never_exposes_empty_raw_storage_value(): void
    {
        $msgId = $this->seedMessage();
        DB::table('message_attachments')->insert([
            'tenant_id'  => self::TENANT_ID,
            'message_id' => $msgId,
            'file_url'   => '',
            'file_path'  => 'uploads/x.txt',
            'file_name'  => 'x.txt',
            'file_type'  => 'file',
            'file_size'  => 0,
        ]);
        $attachment = MessageAttachment::where('message_id', $msgId)->firstOrFail();
        $this->assertSame(
            "/api/v2/messages/{$attachment->message_id}/attachments/{$attachment->id}",
            $attachment->url,
        );
    }

    // -------------------------------------------------------------------
    // Accessor: getNameAttribute
    // -------------------------------------------------------------------

    public function test_name_accessor_returns_file_name_value(): void
    {
        $attachment = $this->makeAttachment(['file_name' => 'report-2026.pdf']);
        $this->assertSame('report-2026.pdf', $attachment->name);
    }

    // -------------------------------------------------------------------
    // Accessor: getSizeAttribute
    // -------------------------------------------------------------------

    public function test_size_accessor_returns_integer_file_size(): void
    {
        $attachment = $this->makeAttachment(['file_size' => 204800]);
        $this->assertSame(204800, $attachment->size);
        $this->assertIsInt($attachment->size);
    }

    public function test_size_accessor_returns_zero_when_not_set(): void
    {
        $msgId = $this->seedMessage();
        DB::table('message_attachments')->insert([
            'tenant_id'  => self::TENANT_ID,
            'message_id' => $msgId,
            'file_url'   => 'https://cdn.example.com/x.txt',
            'file_path'  => 'uploads/x.txt',
            'file_name'  => 'x.txt',
            'file_type'  => 'file',
            'file_size'  => 0,
        ]);
        $attachment = MessageAttachment::where('message_id', $msgId)->firstOrFail();
        $this->assertSame(0, $attachment->size);
    }

    // -------------------------------------------------------------------
    // Accessor: getTypeAttribute — stored value wins
    // -------------------------------------------------------------------

    public function test_type_accessor_returns_image_when_file_type_is_image(): void
    {
        $attachment = $this->makeAttachment([
            'file_type' => 'image',
            'mime_type' => 'image/jpeg',
        ]);
        $this->assertSame('image', $attachment->type);
    }

    public function test_type_accessor_returns_file_when_file_type_is_file(): void
    {
        $attachment = $this->makeAttachment([
            'file_type' => 'file',
            'mime_type' => 'application/pdf',
        ]);
        $this->assertSame('file', $attachment->type);
    }

    // -------------------------------------------------------------------
    // Accessor: getTypeAttribute — MIME fallback for legacy rows
    // -------------------------------------------------------------------

    public function test_type_accessor_falls_back_to_image_from_mime_type(): void
    {
        // Legacy row: file_type is something other than 'image'|'file'
        $msgId = $this->seedMessage();
        DB::table('message_attachments')->insert([
            'tenant_id'  => self::TENANT_ID,
            'message_id' => $msgId,
            'file_url'   => 'https://cdn.example.com/photo.png',
            'file_path'  => 'uploads/photo.png',
            'file_name'  => 'photo.png',
            'file_type'  => 'other',       // not 'image' or 'file'
            'mime_type'  => 'image/png',
            'file_size'  => 512,
        ]);
        $attachment = MessageAttachment::where('message_id', $msgId)->firstOrFail();
        $this->assertSame('image', $attachment->type);
    }

    public function test_type_accessor_falls_back_to_file_for_non_image_mime(): void
    {
        $msgId = $this->seedMessage();
        DB::table('message_attachments')->insert([
            'tenant_id'  => self::TENANT_ID,
            'message_id' => $msgId,
            'file_url'   => 'https://cdn.example.com/doc.docx',
            'file_path'  => 'uploads/doc.docx',
            'file_name'  => 'doc.docx',
            'file_type'  => 'unknown',
            'mime_type'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size'  => 2048,
        ]);
        $attachment = MessageAttachment::where('message_id', $msgId)->firstOrFail();
        $this->assertSame('file', $attachment->type);
    }

    // -------------------------------------------------------------------
    // $appends — all 4 aliases serialised
    // -------------------------------------------------------------------

    public function test_appended_attributes_are_present_in_serialized_array(): void
    {
        $attachment = $this->makeAttachment();
        $arr = $attachment->toArray();

        $this->assertArrayHasKey('url', $arr);
        $this->assertArrayHasKey('name', $arr);
        $this->assertArrayHasKey('size', $arr);
        $this->assertArrayHasKey('type', $arr);
        $this->assertArrayNotHasKey('file_path', $arr);
    }

    // -------------------------------------------------------------------
    // Tenant scope
    // -------------------------------------------------------------------

    public function test_tenant_scope_excludes_other_tenant_rows(): void
    {
        // Insert an attachment under a different tenant directly.
        DB::table('tenants')->updateOrInsert(
            ['id' => 99764 + 1],
            [
                'name'              => 'Other Tenant',
                'slug'              => 'other-99764',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        // Seed a user and message under the OTHER tenant for FK satisfaction
        $otherUserId = DB::table('users')->insertGetId([
            'tenant_id'   => 99764 + 1,
            'name'        => 'Other User',
            'email'       => 'other-attach-' . uniqid() . '@example.test',
            'is_active'   => 1,
            'is_approved' => 1,
            'status'      => 'active',
            'created_at'  => now(),
        ]);
        $otherMsgId = DB::table('messages')->insertGetId([
            'tenant_id'   => 99764 + 1,
            'sender_id'   => $otherUserId,
            'receiver_id' => $otherUserId,
            'subject'     => 'Other',
            'body'        => 'Other body',
            'created_at'  => now(),
        ]);
        $otherId = DB::table('message_attachments')->insertGetId([
            'tenant_id'  => 99764 + 1,
            'message_id' => $otherMsgId,
            'file_url'   => 'https://cdn.example.com/other.pdf',
            'file_path'  => 'uploads/other.pdf',
            'file_name'  => 'other.pdf',
            'file_type'  => 'file',
            'file_size'  => 100,
        ]);

        // Under tenant 99764, that row must be invisible
        TenantContext::setById(self::TENANT_ID);
        $found = MessageAttachment::find($otherId);
        $this->assertNull($found, 'Tenant scope should exclude attachments from another tenant');
    }

    // -------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(HasTenantScope::class, class_uses_recursive(MessageAttachment::class));
    }
}
