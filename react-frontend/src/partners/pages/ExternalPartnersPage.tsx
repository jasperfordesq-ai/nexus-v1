// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — External Protocol Partners
 * Thin wrapper around the admin ExternalPartners module (connections to
 * non-NEXUS platforms: Komunitin, TimeOverflow, Credit Commons).
 */

import { useTranslation } from 'react-i18next';
import Globe from 'lucide-react/icons/globe';
import ExternalPartners from '@/admin/modules/federation/ExternalPartners';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function ExternalPartnersPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.external_partners.title')}
      description={t('pages.external_partners.description')}
      icon={Globe}
      color="accent"
    >
      <div className={EMBED_RESTYLE}>
        <ExternalPartners />
      </div>
    </PartnersPageShell>
  );
}
