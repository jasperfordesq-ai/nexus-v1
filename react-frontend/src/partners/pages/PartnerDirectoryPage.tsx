// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Partner Directory
 *
 * Consolidates the old two-page split (browse the directory vs edit our
 * own listing) into one tabbed page: "Browse partners" embeds the admin
 * PartnerDirectory module, "Our listing" embeds MyProfile. The active
 * tab is deep-linkable via ?tab=profile (used by the Overview checklist).
 */

import { useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import BookUser from 'lucide-react/icons/book-user';
import PartnerDirectory from '@/admin/modules/federation/PartnerDirectory';
import MyProfile from '@/admin/modules/federation/MyProfile';
import { Tabs, Tab } from '@/components/ui';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

type DirectoryTab = 'browse' | 'profile';

export default function PartnerDirectoryPage() {
  const { t } = useTranslation('partners');
  const [searchParams, setSearchParams] = useSearchParams();

  const tab: DirectoryTab = searchParams.get('tab') === 'profile' ? 'profile' : 'browse';

  const onTabChange = useCallback(
    (key: React.Key) => {
      setSearchParams(key === 'profile' ? { tab: 'profile' } : {}, { replace: true });
    },
    [setSearchParams],
  );

  return (
    <PartnersPageShell
      title={t('pages.directory.title')}
      description={t('pages.directory.description')}
      icon={BookUser}
      color="accent"
      toolbar={
        <Tabs
          aria-label={t('pages.directory.tabs_aria')}
          selectedKey={tab}
          onSelectionChange={onTabChange}
          variant="underlined"
          size="sm"
        >
          <Tab key="browse" title={t('pages.directory.tab_browse')} />
          <Tab key="profile" title={t('pages.directory.tab_profile')} />
        </Tabs>
      }
    >
      {tab === 'browse' ? (
        <div className={EMBED_RESTYLE}>
          <PartnerDirectory />
        </div>
      ) : (
        <div className={EMBED_RESTYLE}>
          <MyProfile />
        </div>
      )}
    </PartnersPageShell>
  );
}
