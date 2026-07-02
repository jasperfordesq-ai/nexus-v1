// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Network Settings
 * Thin wrapper around the admin FederationSettings module (the master
 * switches for what this timebank shares with partners).
 */

import { useTranslation } from 'react-i18next';
import Settings from 'lucide-react/icons/settings';
import FederationSettings from '@/admin/modules/federation/FederationSettings';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function NetworkSettingsPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.settings.title')}
      description={t('pages.settings.description')}
      icon={Settings}
      color="accent"
    >
      <div className={EMBED_RESTYLE}>
        <FederationSettings />
      </div>
    </PartnersPageShell>
  );
}
