// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Partner Directory
 * Thin wrapper around the admin PartnerDirectory module (see
 * PartnershipsPage for the pattern rationale).
 */

import { useTranslation } from 'react-i18next';
import BookUser from 'lucide-react/icons/book-user';
import PartnerDirectory from '@/admin/modules/federation/PartnerDirectory';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function PartnerDirectoryPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.directory.title')}
      description={t('pages.directory.description')}
      icon={BookUser}
      color="accent"
    >
      <div className={EMBED_RESTYLE}>
        <PartnerDirectory />
      </div>
    </PartnersPageShell>
  );
}
