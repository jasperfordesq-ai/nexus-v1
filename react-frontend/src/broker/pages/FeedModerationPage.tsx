// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Broker Feed Moderation Page
 *
 * Content moderation is a broker duty, so the broker panel reuses the full
 * admin FeedModeration module untouched, framed in BrokerPageShell. The
 * embedded page owns its own document title and data fetching; the shared
 * EMBED_RESTYLE hides the admin PageHeader's duplicate title block. See
 * SafeguardingPage for the pattern rationale.
 */

import { useTranslation } from 'react-i18next';
import MessageSquare from 'lucide-react/icons/message-square';
import FeedModeration from '@/admin/modules/moderation/FeedModeration';
import { BrokerPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function FeedModerationPage() {
  const { t } = useTranslation('broker');

  return (
    <BrokerPageShell
      title={t('moderation_feed.title')}
      description={t('moderation_feed.description')}
      icon={MessageSquare}
      color="accent"
    >
      <div className={EMBED_RESTYLE}>
        <FeedModeration />
      </div>
    </BrokerPageShell>
  );
}
