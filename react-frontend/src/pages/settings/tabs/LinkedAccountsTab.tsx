// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { GlassCard } from '@/components/ui';
import { SubAccountsManager } from '@/components/subaccounts/SubAccountsManager';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function LinkedAccountsTab() {
  return (
    <div className="space-y-6">
      <GlassCard className="p-6">
        <SubAccountsManager />
      </GlassCard>
    </div>
  );
}
