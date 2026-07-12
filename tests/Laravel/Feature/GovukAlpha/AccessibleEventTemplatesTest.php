<?php
// Copyright Â© 2024â€“2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class AccessibleEventTemplatesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => true], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    public function test_owner_uses_preview_first_html_forms_to_capture_and_create_a_fresh_draft(): void
    {
        $owner = $this->member('Accessible Template Owner');
        $sourceEventId = $this->sourceEvent((int) $owner->id);
        Sanctum::actingAs($owner, ['*']);
        $base = "/{$this->testTenantSlug}/accessible";

        $capturePreview = $this->get("{$base}/events/{$sourceEventId}/template-preview");
        $capturePreview->assertOk()
            ->assertSeeText(__('event_templates.capture_preview_title'))
            ->assertSeeText(__('event_templates.never_copied.people'))
            ->assertSeeText(__('event_templates.never_copied.tickets'));
        self::assertStringContainsString(
            'no-store',
            (string) $capturePreview->headers->get('Cache-Control'),
        );

        $this->accessiblePost("{$base}/events/{$sourceEventId}/templates", [
            'idempotency_key' => 'accessible-template-capture-1',
        ])->assertRedirect("{$base}/events/templates?status=captured");
        $templateId = (int) DB::table('event_templates')
            ->where('source_event_id', $sourceEventId)
            ->value('id');
        self::assertGreaterThan(0, $templateId);

        $this->get("{$base}/events/templates")
            ->assertOk()
            ->assertSeeText('Accessible template source')
            ->assertSeeText(__('event_templates.use_template'));

        $formPath = "{$base}/event-templates/{$templateId}/materialize";
        $this->get($formPath)
            ->assertOk()
            ->assertSee('type="datetime-local"', false)
            ->assertSeeText(__('event_templates.draft_only_title'));

        $start = CarbonImmutable::now('UTC')->addMonths(2)->startOfHour();
        $input = [
            'template_version' => '1',
            'start_time' => $start->format('Y-m-d\TH:i'),
            'end_time' => $start->addHours(2)->format('Y-m-d\TH:i'),
            'title' => 'Accessible fresh draft',
            'location' => 'Accessible new venue',
            'max_attendees' => '20',
            'timezone' => 'UTC',
            'all_day' => '0',
        ];
        $this->accessiblePost("{$formPath}/preview", $input)
            ->assertOk()
            ->assertSeeText(__('event_templates.ready_title'))
            ->assertSeeText(__('event_templates.draft'))
            ->assertSeeText(__('event_templates.never_copied.forms'));

        $this->accessiblePost($formPath, [
            ...$input,
            'idempotency_key' => 'accessible-template-materialize-1',
        ])->assertRedirect();
        $createdEventId = (int) DB::table('event_template_materializations')
            ->where('template_id', $templateId)
            ->value('created_event_id');
        self::assertGreaterThan(0, $createdEventId);
        $this->accessiblePost($formPath, [
            ...$input,
            'idempotency_key' => 'accessible-template-materialize-1',
        ])->assertRedirect("{$base}/events/{$createdEventId}/edit");
        self::assertSame('draft', DB::table('events')->where('id', $createdEventId)->value('publication_status'));
        self::assertSame('none', DB::table('events')->where('id', $createdEventId)->value('federated_visibility'));
        self::assertSame(0, DB::table('event_registrations')->where('event_id', $createdEventId)->count());
    }

    public function test_non_manager_cannot_preview_capture_or_materialize_an_event_template(): void
    {
        $owner = $this->member('Accessible Template Owner Two');
        $member = $this->member('Accessible Template Member');
        $sourceEventId = $this->sourceEvent((int) $owner->id);
        $capture = (new \App\Services\EventTemplateService())->capture(
            $sourceEventId,
            $owner,
            'accessible-template-denied-fixture',
        );
        $templateId = (int) $capture['template']->id;
        Sanctum::actingAs($member, ['*']);
        $base = "/{$this->testTenantSlug}/accessible";

        $this->get("{$base}/events/{$sourceEventId}/template-preview")->assertForbidden();
        $this->get("{$base}/event-templates/{$templateId}/materialize")->assertForbidden();
        self::assertSame(0, DB::table('event_template_materializations')->where('template_id', $templateId)->count());
    }

    private function accessiblePost(string $uri, array $data): TestResponse
    {
        $token = 'accessible-event-template-token';
        $this->withSession(['_token' => $token]);

        return $this->post($uri, ['_token' => $token, ...$data]);
    }

    private function member(string $name): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'name' => $name,
            'first_name' => $name,
            'role' => 'member',
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    private function sourceEvent(int $ownerId): int
    {
        $start = CarbonImmutable::now('UTC')->addMonth()->startOfHour();

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $ownerId,
            'title' => 'Accessible template source',
            'description' => 'Accessible reusable configuration.',
            'location' => 'Original venue',
            'start_time' => $start,
            'end_time' => $start->addHours(2),
            'timezone' => 'UTC',
            'timezone_source' => 'test',
            'all_day' => false,
            'max_attendees' => 40,
            'is_online' => false,
            'allow_remote_attendance' => false,
            'online_link' => 'https://private.example.invalid/accessible',
            'federated_visibility' => 'none',
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 1,
            'calendar_sequence' => 1,
            'is_recurring_template' => false,
            'occurrence_key' => 'accessible-template:' . bin2hex(random_bytes(12)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
