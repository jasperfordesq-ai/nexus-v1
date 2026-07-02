// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Event Webhooks
 * Thin wrapper around the admin Webhooks module (automatic notifications
 * sent to partner systems when things happen here).
 */

import { useTranslation } from 'react-i18next';
import Webhook from 'lucide-react/icons/webhook';
import Webhooks from '@/admin/modules/federation/Webhooks';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function WebhooksPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.webhooks.title')}
      description={t('pages.webhooks.description')}
      icon={Webhook}
      color="warning"
    >
      <div className={EMBED_RESTYLE}>
        <Webhooks />
      </div>
    </PartnersPageShell>
  );
}
