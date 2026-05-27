<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature;

use App\Services\EmailService;
use Mockery;
use Tests\Laravel\TestCase;

class SalesOrderApiTest extends TestCase
{
    public function test_public_sales_order_sends_full_quote_to_configured_recipient(): void
    {
        $emailService = Mockery::mock(EmailService::class);
        $emailService->shouldReceive('send')
            ->once()
            ->withArgs(function (string $to, string $subject, string $body, array $options): bool {
                $this->assertSame('jasper.ford.esq@gmail.com', $to);
                $this->assertStringContainsString('Project NEXUS order enquiry', $subject);
                $this->assertStringContainsString('Civic Network', $subject);
                $this->assertStringContainsString('Ava Murphy', $body);
                $this->assertStringContainsString('Full Platform Hosting', $body);
                $this->assertStringContainsString('Network', $body);
                $this->assertStringContainsString('Managed support', $body);
                $this->assertStringContainsString('We need procurement help.', $body);
                $this->assertSame('Ava Murphy <ava@example.org>', $options['replyTo']);
                $this->assertSame('billing', $options['category']);
                $this->assertTrue($options['allow_missing_tenant']);
                $this->assertArrayHasKey('idempotency_key', $options);

                return true;
            })
            ->andReturn(true);

        $this->app->instance(EmailService::class, $emailService);

        $response = $this->apiPost('/v2/sales/orders', $this->payload());

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'received');
        $this->assertStringStartsWith('NXSO-', (string) $response->json('data.reference'));
    }

    public function test_public_sales_order_validates_contact_email(): void
    {
        $emailService = Mockery::mock(EmailService::class);
        $emailService->shouldNotReceive('send');
        $this->app->instance(EmailService::class, $emailService);

        $payload = $this->payload(['email' => 'not-an-email']);
        $response = $this->apiPost('/v2/sales/orders', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.field', 'email');
    }

    public function test_public_sales_order_accepts_sales_preview_cors_preflight(): void
    {
        $response = $this
            ->withHeader('Origin', 'http://127.0.0.1:4176')
            ->withHeader('Access-Control-Request-Method', 'POST')
            ->withHeader('Access-Control-Request-Headers', 'content-type')
            ->options('/api/v2/sales/orders');

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:4176');
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'contact_name' => 'Ava Murphy',
            'organisation' => 'Civic Network',
            'email' => 'ava@example.org',
            'region' => 'Ireland and UK',
            'note' => 'We need procurement help.',
            'page_url' => 'https://project-nexus.ie/hosting#quote-builder',
            'quote' => [
                'product_line_label' => 'Full Platform Hosting',
                'plan_name' => 'Network',
                'active_member_label' => '30,001 to 100,000 active members',
                'billing_cycle' => 'annual',
                'pricing_mode' => 'published',
                'monthly_recurring_label' => '€4,499',
                'annual_recurring_label' => '€44,990',
                'annual_savings_label' => '€8,998',
                'one_off_label' => '€2,000',
                'first_year_label' => '€46,990',
                'line_items' => [
                    [
                        'label' => 'Network hosting',
                        'amount_label' => '€4,499/mo',
                        'quantity' => 1,
                        'cadence' => 'monthly',
                    ],
                    [
                        'label' => 'Managed support',
                        'amount_label' => '€899/mo',
                        'quantity' => 1,
                        'cadence' => 'monthly',
                    ],
                ],
            ],
        ], $overrides);
    }
}
