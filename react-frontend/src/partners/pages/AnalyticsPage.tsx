// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Analytics
 * Thin wrapper around the admin FederationAnalytics module (charts and
 * numbers for activity with partner timebanks).
 */

import { useTranslation } from 'react-i18next';
import BarChart3 from 'lucide-react/icons/bar-chart-3';
import FederationAnalytics from '@/admin/modules/federation/FederationAnalytics';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function AnalyticsPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.analytics.title')}
      description={t('pages.analytics.description')}
      icon={BarChart3}
      color="accent"
    >
      <div className={EMBED_RESTYLE}>
        <FederationAnalytics />
      </div>
    </PartnersPageShell>
  );
}
