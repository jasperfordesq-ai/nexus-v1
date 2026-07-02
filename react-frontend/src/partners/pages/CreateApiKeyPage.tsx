// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Create API Key
 * Thin wrapper around the admin CreateApiKey wizard. Phase B folds this
 * into a drawer on the API Keys page.
 */

import { useTranslation } from 'react-i18next';
import KeyRound from 'lucide-react/icons/key-round';
import CreateApiKey from '@/admin/modules/federation/CreateApiKey';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function CreateApiKeyPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.create_api_key.title')}
      description={t('pages.create_api_key.description')}
      icon={KeyRound}
      color="warning"
    >
      <div className={EMBED_RESTYLE}>
        <CreateApiKey />
      </div>
    </PartnersPageShell>
  );
}
