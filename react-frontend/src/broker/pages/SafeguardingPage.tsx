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

// Scoped restyle of the embedded admin dashboard (wrapper > root div > PageHeader card):
//  1. Hide the PageHeader's title/description block — the shell above already
//     provides the broker-branded header, and a second <h1> would also hurt a11y.
//  2. Hide the whole PageHeader card when it has no action buttons (the
//     dashboard's loading state renders a header without actions — without
//     this rule an empty card would flash above the spinner).
//  3. Slim the remaining actions-only card down to toolbar padding so it reads
//     as the panel's standard toolbar row.
const EMBED_RESTYLE = [
  '[&>div>div:first-child>div>div:first-child]:hidden',
  '[&>div>div:first-child:not(:has(button))]:hidden',
  '[&>div>div:first-child]:p-2',
].join(' ');

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
