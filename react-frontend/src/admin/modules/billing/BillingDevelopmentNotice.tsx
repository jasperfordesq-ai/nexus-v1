// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useTranslation } from 'react-i18next';

import { Alert } from '@/components/ui';

/**
 * Persistent warning for the super-admin billing tools while their React and
 * Stripe workflows are still being validated for production use.
 */
export function BillingDevelopmentNotice() {
  const { t } = useTranslation('admin_billing');

  return (
    <Alert
      color="warning"
      title={t('billing.development_notice_title')}
      description={t('billing.development_notice_description')}
    />
  );
}

