// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Our Directory Listing
 * Thin wrapper around the admin MyProfile module (this tenant's public
 * entry in the partner directory). Phase B folds this into a tab on the
 * directory page.
 */

import { useTranslation } from 'react-i18next';
import BookUser from 'lucide-react/icons/book-user';
import MyProfile from '@/admin/modules/federation/MyProfile';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function DirectoryProfilePage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.directory_profile.title')}
      description={t('pages.directory_profile.description')}
      icon={BookUser}
      color="accent"
    >
      <div className={EMBED_RESTYLE}>
        <MyProfile />
      </div>
    </PartnersPageShell>
  );
}
