// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Inbound API Partners
 * Thin wrapper around the admin ApiPartnersAdminPage module (external
 * systems — banks, payment processors, municipal software — that call
 * into NEXUS). Route-gated on the partner_api feature.
 */

import { useTranslation } from 'react-i18next';
import Network from 'lucide-react/icons/network';
import ApiPartnersAdminPage from '@/admin/modules/api-partners/ApiPartnersAdminPage';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function InboundApiPartnersPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.inbound_api.title')}
      description={t('pages.inbound_api.description')}
      icon={Network}
      color="warning"
    >
      <div className={EMBED_RESTYLE}>
        <ApiPartnersAdminPage />
      </div>
    </PartnersPageShell>
  );
}
