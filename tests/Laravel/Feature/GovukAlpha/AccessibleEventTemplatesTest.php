<?php
// Copyright Â© 2024â€“2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EventTemplateService;
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
        $this->get("{$base}/event-templates/{$templateId}/history")->assertForbidden();
        self::assertSame(0, DB::table('event_template_materializations')->where('template_id', $templateId)->count());
    }

    public function test_owner_can_traverse_complete_private_template_audit_history(): void
    {
        $owner = $this->member('Accessible Template Auditor');
        $sourceEventId = $this->sourceEvent((int) $owner->id);
        $capture = (new EventTemplateService())->capture(
            $sourceEventId,
            $owner,
            'accessible-template-audit-capture',
        );
        $templateId = (int) $capture['template']->id;
        $versionId = (int) DB::table('event_template_versions')
            ->where('template_id', $templateId)
            ->where('version_number', 1)
            ->value('id');
        $auditIds = [];
        $integrityDigests = [];
        foreach (range(1, 21) as $index) {
            $integrityDigests[$index] = hash('sha256', "accessible-audit-integrity-{$index}");
            $auditIds[] = (int) DB::table('event_template_audit')->insertGetId([
                'tenant_id' => $this->testTenantId,
                'template_id' => $templateId,
                'template_version_id' => $versionId,
                'template_version_number' => 1,
                'source_event_id' => $sourceEventId,
                'materialized_event_id' => null,
                'action' => 'revised',
                'actor_user_id' => (int) $owner->id,
                'idempotency_hash' => hash('sha256', "accessible-audit-idempotency-{$index}"),
                'request_hash' => hash('sha256', "accessible-audit-request-{$index}"),
                'metadata' => json_encode([
                    'schema_version' => 2,
                    'payload_hash' => $integrityDigests[$index],
                    'copied_fields' => ['title'],
                    'skipped_fields' => [],
                    'private_snapshot' => 'DO_NOT_EXPOSE_PRIVATE_AUDIT_METADATA',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now()->addSeconds($index),
            ]);
        }

        Sanctum::actingAs($owner, ['*']);
        $base = "/{$this->testTenantSlug}/accessible";
        $library = $this->get("{$base}/events/templates?filter=all");
        $library->assertOk()
            ->assertSeeText(__('event_templates.audit_link'))
            ->assertSee("/event-templates/{$templateId}/history", false);

        rsort($auditIds);
        $firstPageBoundary = $auditIds[19];
        $firstPage = $this->get(
            "{$base}/event-templates/{$templateId}/history?filter=all&library_cursor=99",
        );
        $firstPage->assertOk()
            ->assertSeeText(__('event_templates.audit_title'))
            ->assertSeeText(__('event_templates.audit_actions.revised'))
            ->assertSeeText(__('event_templates.fields.title'))
            ->assertSeeInOrder(str_split($integrityDigests[21], 8), false)
            ->assertDontSee(substr($integrityDigests[1], 0, 8))
            ->assertDontSee('DO_NOT_EXPOSE_PRIVATE_AUDIT_METADATA')
            ->assertSee('rel="next"', false)
            ->assertSee('filter=all', false)
            ->assertSee('library_cursor=99', false)
            ->assertSee('cursor=99', false);
        self::assertStringContainsString(
            'no-store',
            (string) $firstPage->headers->get('Cache-Control'),
        );

        $lastPage = $this->get(
            "{$base}/event-templates/{$templateId}/history?cursor={$firstPageBoundary}&filter=all&library_cursor=99",
        );
        $lastPage->assertOk()
            ->assertSeeInOrder(str_split($integrityDigests[1], 8), false)
            ->assertSeeText(__('event_templates.audit_actions.captured'))
            ->assertDontSee('rel="next"', false)
            ->assertSee("events/templates?filter=all&amp;cursor=99", false);
    }

    public function test_template_audit_history_validates_cursor_and_filter_context(): void
    {
        $owner = $this->member('Accessible Template Query Validator');
        $sourceEventId = $this->sourceEvent((int) $owner->id);
        $capture = (new EventTemplateService())->capture(
            $sourceEventId,
            $owner,
            'accessible-template-query-validation',
        );
        $templateId = (int) $capture['template']->id;
        Sanctum::actingAs($owner, ['*']);
        $path = "/{$this->testTenantSlug}/accessible/event-templates/{$templateId}/history";

        $this->get("{$path}?cursor=invalid")->assertStatus(422);
        $this->get("{$path}?cursor=0")->assertStatus(422);
        $this->get("{$path}?library_cursor[]=1")->assertStatus(422);
        $this->get("{$path}?filter=unknown")->assertStatus(422);
    }

    public function test_template_audit_history_requires_authentication_feature_and_tenant_identity(): void
    {
        $owner = $this->member('Accessible Template Boundary Owner');
        $sourceEventId = $this->sourceEvent((int) $owner->id);
        $capture = (new EventTemplateService())->capture(
            $sourceEventId,
            $owner,
            'accessible-template-boundary-capture',
        );
        $templateId = (int) $capture['template']->id;
        $base = "/{$this->testTenantSlug}/accessible";

        $this->get("{$base}/event-templates/{$templateId}/history")
            ->assertRedirect("{$base}/login?status=auth-required");

        $foreignTenant = Tenant::factory()->create([
            'features' => json_encode(['events' => true], JSON_THROW_ON_ERROR),
        ]);
        $foreignOwner = User::factory()->forTenant((int) $foreignTenant->id)->create([
            'role' => 'member',
            'status' => 'active',
            'is_approved' => true,
        ]);
        TenantContext::reset();
        TenantContext::setById((int) $foreignTenant->id);
        $foreignSourceEventId = $this->sourceEvent(
            (int) $foreignOwner->id,
            (int) $foreignTenant->id,
        );
        $foreignCapture = (new EventTemplateService())->capture(
            $foreignSourceEventId,
            $foreignOwner,
            'accessible-template-foreign-capture',
        );

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        Sanctum::actingAs($owner, ['*']);
        $this->get("{$base}/event-templates/{$foreignCapture['template']->id}/history")
            ->assertNotFound();

        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => false], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->get("{$base}/event-templates/{$templateId}/history")->assertForbidden();
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

    private function sourceEvent(int $ownerId, ?int $tenantId = null): int
    {
        $start = CarbonImmutable::now('UTC')->addMonth()->startOfHour();
        $tenantId ??= $this->testTenantId;

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $tenantId,
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
