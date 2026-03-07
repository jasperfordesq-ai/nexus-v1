// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * DevelopmentStatusBanner
 *
 * A persistent, non-dismissible top banner showing the platform's current
 * release stage (RC, beta, etc.). Renders on all pages — authenticated,
 * public, and admin.
 *
 * Uses a plain absolute path for the "Read more" link so it works in any
 * layout context (tenant-scoped or admin) without requiring TenantContext.
 */

import { Link } from 'react-router-dom';
import { FlaskConical } from 'lucide-react';
import { RELEASE_STATUS } from '@/config/releaseStatus';

export function DevelopmentStatusBanner() {
  return (
    <div
      role="region"
      aria-label="Development status"
      className="relative z-10 w-full bg-amber-50 dark:bg-amber-950 border-b border-amber-200 dark:border-amber-800 py-1 px-4 text-center"
    >
      <p className="text-amber-900 dark:text-amber-100 text-xs flex items-center justify-center gap-1.5 flex-wrap">
        <FlaskConical className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
        <span>
          <span className="font-semibold">Development status: {RELEASE_STATUS.stageLabel}</span>
          {' — '}
          {RELEASE_STATUS.stageSummary}
        </span>
        <Link
          to={RELEASE_STATUS.readMorePath}
          className="underline font-medium ml-1 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded whitespace-nowrap"
        >
          Read more
        </Link>
      </p>
    </div>
  );
}

export default DevelopmentStatusBanner;
