// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Timebanks — Credit Agreements
 * Thin wrapper around the admin CreditAgreements module (credit limits
 * agreed with partner timebanks).
 */

import { useTranslation } from 'react-i18next';
import Scale from 'lucide-react/icons/scale';
import CreditAgreements from '@/admin/modules/federation/CreditAgreements';
import { PartnersPageShell } from '../components';
import { EMBED_RESTYLE } from '../components/adminEmbed';

export default function CreditAgreementsPage() {
  const { t } = useTranslation('partners');

  return (
    <PartnersPageShell
      title={t('pages.credit_agreements.title')}
      description={t('pages.credit_agreements.description')}
      icon={Scale}
      color="accent"
    >
      <div className={EMBED_RESTYLE}>
        <CreditAgreements />
      </div>
    </PartnersPageShell>
  );
}
