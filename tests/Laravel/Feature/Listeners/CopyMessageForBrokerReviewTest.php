<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Listeners;

use App\Core\TenantContext;
use App\Events\MessageSent;
use App\Listeners\CopyMessageForBrokerReview;
use App\Models\Message;
use App\Models\User;
use App\Services\BrokerMessageVisibilityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;

/**
 * Feature tests for CopyMessageForBrokerReview listener.
 *
 * Verifies listener registration, tenant context propagation,
 * conditional copy behaviour, and exception swallowing.
 */
class CopyMessageForBrokerReviewTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // REGISTRATION
    // ================================================================

    public function test_listener_is_registered_for_message_sent_event(): void
    {
        $listeners = Event::getListeners(MessageSent::class);

        // Event::getListeners returns closures / class strings — check for our class
        $found = false;
        foreach ($listeners as $listener) {
            $listenerStr = is_string($listener) ? $listener : (is_array($listener) ? implode('@', $listener) : '');
            if (str_contains($listenerStr, 'CopyMessageForBrokerReview')) {
                $found = true;
                break;
            }
            // Laravel wraps queued listeners in a closure — check via reflection
            if ($listener instanceof \Closure) {
                try {
                    $ref = new \ReflectionFunction($listener);
                    $source = $ref->getStaticVariables();
                    if (isset($source['listener']) && str_contains($source['listener'], 'CopyMessageForBrokerReview')) {
                        $found = true;
                        break;
                    }
                } catch (\ReflectionException) {
                    // Ignore
                }
            }
        }

        // Alternatively assert via EventServiceProvider's $listen map directly
        if (!$found) {
            /** @var \App\Providers\EventServiceProvider $provider */
            $provider = app()->getProvider(\App\Providers\EventServiceProvider::class);
            $listen = $provider->listens();
            $messageSentListeners = $listen[MessageSent::class] ?? [];
            $this->assertContains(
                CopyMessageForBrokerReview::class,
                $messageSentListeners,
                'CopyMessageForBrokerReview must be registered as a listener for MessageSent'
            );
        } else {
            $this->assertTrue($found);
        }
    }

    // ================================================================
    // TENANT CONTEXT
    // ================================================================

    public function test_listener_sets_tenant_context_from_event(): void
    {
        // Start with a different context to prove it gets overridden
        TenantContext::setById(999);

        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $message = new Message([
            'id'          => 99999,
            'tenant_id'   => $this->testTenantId,
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'body'        => 'Hello',
            'listing_id'  => null,
        ]);
        $message->exists = true;

        $capturedTenantId = null;

        // Mock BrokerMessageVisibilityService so we can assert tenant context mid-call
        $mockService = \Mockery::mock(BrokerMessageVisibilityService::class);
        $mockService->shouldReceive('shouldCopyMessage')
            ->once()
            ->andReturnUsing(function () use (&$capturedTenantId) {
                $capturedTenantId = TenantContext::getId();
                return null;
            });

        $this->app->instance(BrokerMessageVisibilityService::class, $mockService);

        $listener = new CopyMessageForBrokerReview();
        $event = new MessageSent($message, $sender, 1, $this->testTenantId);
        $listener->handle($event);

        $this->assertEquals($this->testTenantId, $capturedTenantId,
            'TenantContext must be set to the event tenantId before the service is called'
        );
    }

    // ================================================================
    // CONDITIONAL COPY
    // ================================================================

    public function test_listener_calls_copy_when_should_copy_returns_a_reason(): void
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $message = new Message([
            'id'          => 88881,
            'tenant_id'   => $this->testTenantId,
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'body'        => 'Test message',
            'listing_id'  => null,
        ]);
        $message->exists = true;

        $mockService = \Mockery::mock(BrokerMessageVisibilityService::class);
        $mockService->shouldReceive('shouldCopyMessage')
            ->once()
            ->with($sender->id, $receiver->id, null)
            ->andReturn('new_member');
        $mockService->shouldReceive('copyMessageForBroker')
            ->once()
            ->with(88881, 'new_member');

        $this->app->instance(BrokerMessageVisibilityService::class, $mockService);

        $listener = new CopyMessageForBrokerReview();
        $event = new MessageSent($message, $sender, 1, $this->testTenantId);
        $listener->handle($event);

        // Mockery expectations are verified in tearDown via Mockery::close()
        $this->assertTrue(true);
    }

    public function test_listener_does_not_call_copy_when_should_copy_returns_null(): void
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $message = new Message([
            'id'          => 88882,
            'tenant_id'   => $this->testTenantId,
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'body'        => 'Normal message',
            'listing_id'  => null,
        ]);
        $message->exists = true;

        $mockService = \Mockery::mock(BrokerMessageVisibilityService::class);
        $mockService->shouldReceive('shouldCopyMessage')
            ->once()
            ->andReturn(null);
        $mockService->shouldNotReceive('copyMessageForBroker');

        $this->app->instance(BrokerMessageVisibilityService::class, $mockService);

        $listener = new CopyMessageForBrokerReview();
        $event = new MessageSent($message, $sender, 1, $this->testTenantId);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    // ================================================================
    // EXCEPTION SAFETY
    // ================================================================

    public function test_listener_swallows_exceptions_without_rethrow(): void
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $message = new Message([
            'id'          => 88883,
            'tenant_id'   => $this->testTenantId,
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'body'        => 'Message that will throw',
            'listing_id'  => null,
        ]);
        $message->exists = true;

        // Service that throws a runtime exception
        $mockService = \Mockery::mock(BrokerMessageVisibilityService::class);
        $mockService->shouldReceive('shouldCopyMessage')
            ->once()
            ->andThrow(new \RuntimeException('DB connection lost'));

        $this->app->instance(BrokerMessageVisibilityService::class, $mockService);

        $listener = new CopyMessageForBrokerReview();
        $event = new MessageSent($message, $sender, 1, $this->testTenantId);

        // Must NOT throw — message delivery must never be blocked by broker copy failures
        $exceptionThrown = false;
        try {
            $listener->handle($event);
        } catch (\Throwable) {
            $exceptionThrown = true;
        }

        $this->assertFalse($exceptionThrown,
            'CopyMessageForBrokerReview must swallow exceptions to avoid blocking message delivery'
        );
    }

    public function test_listener_logs_error_when_exception_occurs(): void
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        $message = new Message([
            'id'          => 88884,
            'tenant_id'   => $this->testTenantId,
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'body'        => 'Failing message',
            'listing_id'  => null,
        ]);
        $message->exists = true;

        $mockService = \Mockery::mock(BrokerMessageVisibilityService::class);
        $mockService->shouldReceive('shouldCopyMessage')
            ->once()
            ->andThrow(new \RuntimeException('Test error'));

        $this->app->instance(BrokerMessageVisibilityService::class, $mockService);

        Log::shouldReceive('error')
            ->once()
            ->with('CopyMessageForBrokerReview: failed', \Mockery::type('array'));

        $listener = new CopyMessageForBrokerReview();
        $event = new MessageSent($message, $sender, 1, $this->testTenantId);
        $listener->handle($event);

        // Log assertion verified by Mockery
        $this->assertTrue(true);
    }
}
