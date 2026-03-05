<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class NewsletterApiControllerTest extends ApiTestCase
{
    public function testSubscribe(): void
    {
        $response = $this->post('/api/v2/newsletter/subscribe', [
            'email' => 'subscriber@test.com',
        ], [], 'Nexus\Controllers\Api\NewsletterApiController@subscribe');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testUnsubscribe(): void
    {
        $response = $this->post('/api/v2/newsletter/unsubscribe', [
            'token' => 'test_unsub_token',
        ], [], 'Nexus\Controllers\Api\NewsletterApiController@unsubscribe');

        $this->assertIsArray($response);
    }
}
