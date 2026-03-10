// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Chatrooms Tab (I4)
 * Renders the TeamChatrooms ideation component for a group.
 */

import { GlassCard } from '@/components/ui';
import { TeamChatrooms } from '@/components/ideation';

interface GroupChatroomsTabProps {
  groupId: number;
  isGroupAdmin: boolean;
}

export function GroupChatroomsTab({ groupId, isGroupAdmin }: GroupChatroomsTabProps) {
  return (
    <GlassCard className="p-6">
      <TeamChatrooms groupId={groupId} isGroupAdmin={isGroupAdmin} />
    </GlassCard>
  );
}
