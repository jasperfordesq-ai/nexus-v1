// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { ReportProblemButton } from '@/components/feedback/ReportProblemButton';
import { useAuth } from '@/contexts';

export function FloatingReportProblemButton() {
  const { isAuthenticated } = useAuth();

  // Logged-in users only — keeps anonymous traffic from spamming support reports / Sentry.
  if (!isAuthenticated) {
    return null;
  }

  return (
    <div
      className="fixed bottom-6 right-6 z-280 hidden md:block"
      data-testid="floating-report-problem"
    >
      <ReportProblemButton
        className="h-12 rounded-full border border-[var(--border-default)] bg-[var(--surface-elevated)] px-5 shadow-xl shadow-black/15 backdrop-blur-sm hover:bg-theme-hover"
      />
    </div>
  );
}

export default FloatingReportProblemButton;
