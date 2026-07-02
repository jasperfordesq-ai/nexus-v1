// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — API Keys
 * Thin wrapper around the admin ApiKeys module (keys partner platforms
 * use to call this timebank). Phase B replaces the separate create page
 * with an in-page drawer.
 */

import { useTranslation } from 'react-i18next';
import KeyRound from 'lucide-react/icons/key-round';
import ApiKeys from '@/admin/modules/federation/ApiKeys';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function ApiKeysPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.api_keys.title')}
      description={t('pages.api_keys.description')}
      icon={KeyRound}
      color="warning"
    >
      <div className={EMBED_RESTYLE}>
        <ApiKeys />
      </div>
    </PartnersPageShell>
  );
}
