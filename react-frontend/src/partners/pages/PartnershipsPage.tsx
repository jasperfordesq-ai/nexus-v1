// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Partnerships
 * Thin wrapper: reuses the admin Partnerships module untouched, framed in
 * PartnersPageShell. The embedded page owns its own document title and data
 * fetching; EMBED_RESTYLE hides the admin PageHeader's duplicate title block.
 */

import { useTranslation } from 'react-i18next';
import Handshake from 'lucide-react/icons/handshake';
import Partnerships from '@/admin/modules/federation/Partnerships';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function PartnershipsPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.partnerships.title')}
      description={t('pages.partnerships.description')}
      icon={Handshake}
      color="accent"
    >
      <div className={EMBED_RESTYLE}>
        <Partnerships />
      </div>
    </PartnersPageShell>
  );
}
