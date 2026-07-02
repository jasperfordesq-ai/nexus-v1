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
 *
 * To make the shared dashboard feel native here, it is framed in the broker
 * BrokerPageShell (danger domain, shield icon, broker-namespace copy). The
 * admin component renders its own PageHeader card, which would duplicate the
 * shell's title — the admin module must NOT be forked or edited, so the
 * duplicate title/description block is suppressed with scoped CSS while the
 * header's action buttons (Refresh / New assignment) stay visible as a slim
 * toolbar card. If the admin PageHeader markup ever changes, the worst-case
 * failure mode is cosmetic (the duplicate header reappears) — no behaviour
 * or functionality is ever lost.
 *
 * The embedded dashboard keeps owning the document title (usePageTitle) and
 * all data fetching/permissions exactly as before.
 */

import { useTranslation } from 'react-i18next';
import Shield from 'lucide-react/icons/shield';
import { SafeguardingDashboard } from '@/admin/modules/safeguarding/SafeguardingDashboard';
import { BrokerPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function SafeguardingPage() {
  const { t } = useTranslation('broker');

  return (
    <BrokerPageShell
      title={t('safeguarding.title')}
      description={t('safeguarding.description')}
      icon={Shield}
      color="danger"
    >
      <div className={EMBED_RESTYLE}>
        <SafeguardingDashboard routeBase="/broker/safeguarding" />
      </div>
    </BrokerPageShell>
  );
}
