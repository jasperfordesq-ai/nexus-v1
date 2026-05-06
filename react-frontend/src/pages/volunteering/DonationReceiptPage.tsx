// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useParams } from 'react-router-dom';
import { DonationReceipt } from '@/components/donations/DonationReceipt';
import { usePageTitle } from '@/hooks';
import { useTranslation } from 'react-i18next';

export default function DonationReceiptPage() {
  const { id } = useParams<{ id: string }>();
  const { t } = useTranslation('volunteering');
  usePageTitle(t('donations.receipt_title', 'Donation Receipt'));

  return (
    <div className="mx-auto max-w-3xl px-4 py-8">
      <DonationReceipt donationId={Number(id ?? 0)} />
    </div>
  );
}
