// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Safeguarding Page
 *
 * The broker panel intentionally reuses the full admin safeguarding dashboard
 * so flagged messages, guardian assignments, member preferences, and future
 * safeguarding fixes remain in parity across both admin surfaces.
 */

import { SafeguardingDashboard } from '@/admin/modules/safeguarding/SafeguardingDashboard';

export default function SafeguardingPage() {
  return <SafeguardingDashboard routeBase="/broker/safeguarding" />;
}
