<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupModerationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GroupModerationServiceTest extends TestCase
{
    public function test_action_constants_are_defined(): void
    {
        $this->assertEquals('flag', GroupModerationService::ACTION_FLAG);
        $this->assertEquals('hide', GroupModerationService::ACTION_HIDE);
        $this->assertEquals('delete', GroupModerationService::ACTION_DELETE);
        $this->assertEquals('approve', GroupModerationService::ACTION_APPROVE);
    }

    public function test_content_type_constants_are_defined(): void
    {
        $this->assertEquals('group', GroupModerationService::CONTENT_GROUP);
        $this->assertEquals('discussion', GroupModerationService::CONTENT_DISCUSSION);
        $this->assertEquals('post', GroupModerationService::CONTENT_POST);
    }

    public function test_reason_constants_are_defined(): void
    {
        $this->assertEquals('spam', GroupModerationService::REASON_SPAM);
        $this->assertEquals('harassment', GroupModerationService::REASON_HARASSMENT);
        $this->assertEquals('inappropriate', GroupModerationService::REASON_INAPPROPRIATE);
        $this->assertEquals('hate_speech', GroupModerationService::REASON_HATE_SPEECH);
        $this->assertEquals('other', GroupModerationService::REASON_OTHER);
    }

    public function test_flagContent_returns_id_on_success(): void
    {
        DB::shouldReceive('table->insertGetId')->andReturn(5);

        $result = GroupModerationService::flagContent('post', 1, 10, 'spam', 'This is spam');
        $this->assertEquals(5, $result);
    }

    public function test_flagContent_returns_null_on_failure(): void
    {
        DB::shouldReceive('table->insertGetId')->andThrow(new \Exception('error'));
        Log::shouldReceive('warning')->once();

        $result = GroupModerationService::flagContent('post', 1, 10);
        $this->assertNull($result);
    }

    public function test_moderateContent_returns_false_when_flag_not_found(): void
    {
        DB::shouldReceive('table->where->first')->andReturn(null);

        $result = GroupModerationService::moderateContent(999, 'approve', 10);
        $this->assertFalse($result);
    }
}
