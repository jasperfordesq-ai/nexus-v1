// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Shared Aggregates
 * Thin wrapper around the admin FederationAggregatesPage module (consent
 * for sharing anonymous, aggregated statistics with the wider network).
 */

import { useTranslation } from 'react-i18next';
import ShieldCheck from 'lucide-react/icons/shield-check';
import FederationAggregatesPage from '@/admin/modules/federation/FederationAggregatesPage';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function AggregatesPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.aggregates.title')}
      description={t('pages.aggregates.description')}
      icon={ShieldCheck}
      color="neutral"
    >
      <div className={EMBED_RESTYLE}>
        <FederationAggregatesPage />
      </div>
    </PartnersPageShell>
  );
}
