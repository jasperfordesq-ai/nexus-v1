// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Developer Guide
 * Thin wrapper around the admin ApiDocumentation module (reference docs
 * for the Federation API, aimed at partner developers).
 */

import { useTranslation } from 'react-i18next';
import BookOpen from 'lucide-react/icons/book-open';
import ApiDocumentation from '@/admin/modules/federation/ApiDocumentation';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function ApiDocsPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.api_docs.title')}
      description={t('pages.api_docs.description')}
      icon={BookOpen}
      color="neutral"
    >
      <div className={EMBED_RESTYLE}>
        <ApiDocumentation />
      </div>
    </PartnersPageShell>
  );
}
