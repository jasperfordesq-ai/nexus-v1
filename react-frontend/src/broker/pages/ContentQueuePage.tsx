// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Content Queue Page
 *
 * Reuses the admin content-moderation queue (ModerationQueuePage) untouched
 * inside the broker shell. The queue itself is broker-or-admin, but its
 * moderation SETTINGS stay admin-only (tenant policy) — so for broker-role
 * users the embedded page's "Settings" header button is hidden via scoped
 * CSS (EMBED_HIDE_FIRST_HEADER_BUTTON). The API still 403s if the CSS ever
 * fails to match, so this is cosmetic defence only. Admins keep the button.
 */

import { useTranslation } from 'react-i18next';
import Shield from 'lucide-react/icons/shield';
import ModerationQueuePage from '@/admin/modules/reports/ModerationQueuePage';
import { useAuth } from '@/contexts';
import { hasAdminPanelAccess } from '@/lib/access';
import { BrokerPageShell } from '../components';
import { EMBED_RESTYLE, EMBED_HIDE_FIRST_HEADER_BUTTON } from '../components/adminEmbed';

export default function ContentQueuePage() {
  const { t } = useTranslation('broker');
  const { user } = useAuth();
  const isAdmin = hasAdminPanelAccess(user);

  const className = isAdmin ? EMBED_RESTYLE : `${EMBED_RESTYLE} ${EMBED_HIDE_FIRST_HEADER_BUTTON}`;

  return (
    <BrokerPageShell
      title={t('moderation_queue.title')}
      description={t('moderation_queue.description')}
      icon={Shield}
      color="accent"
    >
      <div className={className}>
        <ModerationQueuePage />
      </div>
    </BrokerPageShell>
  );
}
