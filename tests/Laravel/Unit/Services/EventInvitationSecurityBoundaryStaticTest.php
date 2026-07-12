<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use PHPUnit\Framework\TestCase;

final class EventInvitationSecurityBoundaryStaticTest extends TestCase
{
    public function test_preview_issue_and_delivery_share_one_recipient_authorization_boundary(): void
    {
        $authorizer = $this->source(
            'app/Support/Events/EventInvitationRecipientAuthorizer.php',
        );
        $preview = $this->source('app/Services/EventInvitationCampaignService.php');
        $issue = $this->source('app/Services/EventInvitationService.php');
        $delivery = $this->source('app/Services/EventInvitationDeliveryConsumer.php');

        self::assertStringContainsString('EventPolicy $eventPolicy', $authorizer);
        self::assertStringContainsString('$this->eventPolicy->view($target, $event)', $authorizer);
        self::assertStringContainsString("'user_blocks'", $authorizer);
        self::assertStringContainsString("'tenant_id', \$tenantId", $authorizer);
        self::assertStringContainsString('evaluateLocalContact(', $authorizer);
        self::assertStringContainsString('$actorId,', $authorizer);
        self::assertStringContainsString('$targetId,', $authorizer);
        self::assertStringContainsString("'event_invitation'", $authorizer);
        self::assertStringContainsString('filterPreview(', $preview);
        self::assertStringContainsString('assertEligibleForIssue(', $issue);
        self::assertStringContainsString('deliveryDecision(', $delivery);
        self::assertStringContainsString("'invitation_target_ineligible'", $delivery);
        self::assertStringContainsString('event_invitation_target_policy_unavailable', $delivery);
    }

    public function test_private_event_access_is_never_inferred_from_an_invitation(): void
    {
        $authorizer = $this->source(
            'app/Support/Events/EventInvitationRecipientAuthorizer.php',
        );
        $eventPolicy = $this->source('app/Policies/EventPolicy.php');

        self::assertStringContainsString(
            'An invitation is contact evidence, never an audience-access',
            $authorizer,
        );
        self::assertStringContainsString("EventPublicationState::Published->value", $authorizer);
        self::assertStringContainsString(
            '(string) $group->visibility === \'public\'',
            $authorizer,
        );
        self::assertStringNotContainsString('EventInvitation', $eventPolicy);
        self::assertStringNotContainsString('event_invitations', $eventPolicy);
    }

    public function test_every_group_derived_source_requires_current_member_management_authority(): void
    {
        $expander = $this->source(
            'app/Support/Events/EventInvitationRecipientExpander.php',
        );
        $issue = $this->source('app/Services/EventInvitationService.php');

        self::assertStringContainsString('User|int $actor', $expander);
        self::assertStringContainsString('GroupAccessService::canManageMembers', $expander);
        self::assertStringContainsString('assertGroupSourceAuthority(', $expander);
        self::assertStringContainsString('event_invitation_group_not_found', $expander);
        self::assertStringContainsString('event_invitation_audience_group_not_found', $expander);
        self::assertStringContainsString('assertSnapshotSourceAuthority(', $issue);
    }

    private function source(string $relative): string
    {
        $path = dirname(__DIR__, 4) . '/' . $relative;
        $source = file_get_contents($path);
        self::assertIsString($source, "Could not read {$relative}");

        return $source;
    }
}
