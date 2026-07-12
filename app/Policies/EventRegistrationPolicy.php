<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/** Registration-specific abilities; sensitive answers never inherit broad staff access. */
final class EventRegistrationPolicy
{
    public function __construct(private readonly EventPolicy $events = new EventPolicy())
    {
    }

    public function designForms(User $actor, Event $event): bool
    {
        return $this->events->manageRegistration($actor, $event);
    }

    public function manageInvitations(User $actor, Event $event): bool
    {
        return $this->events->manageRegistration($actor, $event);
    }

    public function reviewAnswers(User $actor, Event $event): bool
    {
        return $this->events->manageRegistration($actor, $event);
    }

    public function exportAnswers(User $actor, Event $event): bool
    {
        return $this->events->manageRegistration($actor, $event)
            && $this->events->exportPeople($actor, $event);
    }

    /** Only the event owner or tenant-level administrators receive this boundary. */
    public function viewSensitiveAnswers(User $actor, Event $event): bool
    {
        return $this->events->transferOwnership($actor, $event);
    }

    public function manageRetention(User $actor, Event $event): bool
    {
        return $this->events->transferOwnership($actor, $event);
    }
}
