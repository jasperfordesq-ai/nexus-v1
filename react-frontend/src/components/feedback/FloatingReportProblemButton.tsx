// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { ReportProblemButton } from '@/components/feedback/ReportProblemButton';

export function FloatingReportProblemButton() {
  return (
    <div
      className="fixed bottom-[calc(var(--safe-area-bottom)+5.25rem)] right-3 z-280 md:bottom-6 md:right-6"
      data-testid="floating-report-problem"
    >
      <ReportProblemButton className="h-12 rounded-full border border-[var(--border-default)] bg-[var(--surface-elevated)] px-4 shadow-xl shadow-black/15 backdrop-blur-sm hover:bg-theme-hover md:px-5" />
    </div>
  );
}

export default FloatingReportProblemButton;
