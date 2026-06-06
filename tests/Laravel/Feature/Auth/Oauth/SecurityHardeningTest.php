<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Auth\Oauth;

use App\Models\User;
use App\Services\Auth\SocialAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

class SecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_oauth_callback_code_is_single_use(): void
    {
        $service = app(SocialAuthService::class);

        $code = $service->issueCallbackCode('plain-sanctum-token', 'google', false, $this->testTenantId);

        $payload = $service->consumeCallbackCode($code);
        $this->assertSame('plain-sanctum-token', $payload['token']);
        $this->assertSame('google', $payload['provider']);
        $this->assertSame($this->testTenantId, $payload['tenant_id']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OAuth callback code is invalid or expired.');
        $service->consumeCallbackCode($code);
    }

    public function test_link_callback_requires_initiating_browser_nonce(): void
    {
        $service = app(SocialAuthService::class);
        $user = User::factory()->forTenant($this->testTenantId)->create();

        $state = $this->callPrivateMethod($service, 'buildState', [$this->testTenantId, 'link', (int) $user->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OAuth link state is not bound to this browser session.');
        $service->handleCallback('google', $state);
    }

    /**
     * @param array<int, mixed> $args
     */
    private function callPrivateMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $reflectedMethod = $reflection->getMethod($method);
        $reflectedMethod->setAccessible(true);

        return $reflectedMethod->invokeArgs($object, $args);
    }
}
