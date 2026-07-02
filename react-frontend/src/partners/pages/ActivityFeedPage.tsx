// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Activity Feed
 * Thin wrapper around the admin federation ActivityFeed module (a running
 * log of everything exchanged with partner timebanks).
 */

import { useTranslation } from 'react-i18next';
import Activity from 'lucide-react/icons/activity';
import ActivityFeed from '@/admin/modules/federation/ActivityFeed';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function ActivityFeedPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.activity.title')}
      description={t('pages.activity.description')}
      icon={Activity}
      color="accent"
    >
      <div className={EMBED_RESTYLE}>
        <ActivityFeed />
      </div>
    </PartnersPageShell>
  );
}
