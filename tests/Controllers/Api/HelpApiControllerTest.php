<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class HelpApiControllerTest extends ApiTestCase
{
    public function testGetFaqs(): void
    {
        $response = $this->get('/api/v2/help/faqs', [], [],
            'Nexus\Controllers\Api\HelpApiController@getFaqs');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testAdminGetFaqs(): void
    {
        $response = $this->get('/api/v2/admin/help/faqs', [], [],
            'Nexus\Controllers\Api\HelpApiController@adminGetFaqs');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testAdminCreateFaq(): void
    {
        $response = $this->post('/api/v2/admin/help/faqs', [
            'question' => 'How do I get started?',
            'answer'   => 'Sign up and explore the platform.',
            'category' => 'Getting Started',
        ], [], 'Nexus\Controllers\Api\HelpApiController@adminCreateFaq');

        $this->assertIsArray($response);
    }

    public function testAdminCreateFaqRequiresQuestion(): void
    {
        $response = $this->post('/api/v2/admin/help/faqs', [
            'answer' => 'An answer without a question.',
        ], [], 'Nexus\Controllers\Api\HelpApiController@adminCreateFaq');

        $this->assertIsArray($response);
    }

    public function testAdminDeleteFaq(): void
    {
        $response = $this->delete('/api/v2/admin/help/faqs/999', [], [],
            'Nexus\Controllers\Api\HelpApiController@adminDeleteFaq');

        $this->assertIsArray($response);
    }
}
