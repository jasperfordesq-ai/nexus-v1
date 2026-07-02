// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Neighbourhoods
 * Thin wrapper around the admin Neighborhoods module (geographic
 * groupings that can be shared with partner timebanks).
 */

import { useTranslation } from 'react-i18next';
import MapPin from 'lucide-react/icons/map-pin';
import Neighborhoods from '@/admin/modules/federation/Neighborhoods';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function NeighborhoodsPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.neighborhoods.title')}
      description={t('pages.neighborhoods.description')}
      icon={MapPin}
      color="accent"
    >
      <div className={EMBED_RESTYLE}>
        <Neighborhoods />
      </div>
    </PartnersPageShell>
  );
}
