<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Policies;

use App\Core\TenantContext;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use Throwable;

/**
 * Fail-closed read and UI capability policy for reusable event templates.
 *
 * Mutation services independently re-authorize the persisted source event
 * inside their transaction. This policy is therefore never the sole guard on
 * a write and cannot become stale between a preview and confirmation.
 */
final class EventTemplatePolicy
{
    public function __construct(
        private readonly EventPolicy $events = new EventPolicy(),
    ) {}

    public function createFrom(User $user, Event $event): bool
    {
        return $this->validActor($user) && $this->events->manage($user, $event);
    }

    public function view(User $user, EventTemplate $template): bool
    {
        return $this->decisions($user, [$template])[(int) $template->getKey()] ?? false;
    }

    public function revise(User $user, EventTemplate $template): bool
    {
        return $this->view($user, $template)
            && (string) $template->getRawOriginal('status') === 'active';
    }

    public function archive(User $user, EventTemplate $template): bool
    {
        return $this->revise($user, $template);
    }

    public function materialize(User $user, EventTemplate $template): bool
    {
        return $this->revise($user, $template);
    }

    public function viewAudit(User $user, EventTemplate $template): bool
    {
        return $this->view($user, $template);
    }

    /**
     * Resolve collection decisions through EventPolicy's bounded-query matrix.
     *
     * @param iterable<EventTemplate> $templates
     * @return array<int, bool>
     */
    public function decisions(User $user, iterable $templates): array
    {
        $templatesById = [];
        $eventsById = [];
        foreach ($templates as $template) {
            $templateId = (int) $template->getKey();
            if ($templateId <= 0 || ! $this->templateIdentityMatches($user, $template)) {
                if ($templateId > 0) {
                    $templatesById[$templateId] = null;
                }
                continue;
            }

            try {
                $source = $template->relationLoaded('sourceEvent')
                    ? $template->sourceEvent
                    : $template->sourceEvent()->withoutGlobalScopes()->first();
            } catch (Throwable) {
                $source = null;
            }
            if (! $source instanceof Event
                || (int) $source->getAttribute('tenant_id') !== (int) $user->tenant_id) {
                $templatesById[$templateId] = null;
                continue;
            }

            $templatesById[$templateId] = (int) $source->getKey();
            $eventsById[(int) $source->getKey()] = $source;
        }

        if (! $this->validActor($user) || $eventsById === []) {
            return array_fill_keys(array_keys($templatesById), false);
        }

        try {
            $abilities = $this->events->abilitiesForEvents($user, array_values($eventsById));
        } catch (Throwable) {
            return array_fill_keys(array_keys($templatesById), false);
        }

        $decisions = [];
        foreach ($templatesById as $templateId => $sourceEventId) {
            $decisions[$templateId] = $sourceEventId !== null
                && (bool) ($abilities[$sourceEventId]['manage'] ?? false);
        }

        return $decisions;
    }

    private function validActor(User $user): bool
    {
        $tenantId = TenantContext::currentId();
        if ($tenantId === null
            || $tenantId <= 0
            || (int) $user->getKey() <= 0
            || (int) $user->getAttribute('tenant_id') !== $tenantId
            || (string) $user->getAttribute('status') !== 'active'
            || $user->getAttribute('deleted_at') !== null) {
            return false;
        }

        try {
            return TenantContext::hasFeature('events');
        } catch (Throwable) {
            return false;
        }
    }

    private function templateIdentityMatches(User $user, EventTemplate $template): bool
    {
        $tenantId = TenantContext::currentId();

        return $tenantId !== null
            && $tenantId > 0
            && (int) $template->getKey() > 0
            && (int) $template->getAttribute('tenant_id') === $tenantId
            && (int) $user->getAttribute('tenant_id') === $tenantId;
    }
}
