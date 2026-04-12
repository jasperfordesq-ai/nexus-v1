// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * DonationReceipt - Displays and prints a donation receipt
 *
 * Fetches receipt data from the API and renders it in a print-friendly
 * layout using HeroUI Card with @media print styles.
 */

import { useEffect, useState } from 'react';
import { Button, Card, CardBody, CardHeader, Chip, Spinner } from '@heroui/react';
import { AlertTriangle, Printer, Receipt } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ───────────────────────── Types ───────────────────────── */

interface ReceiptData {
  id: number;
  donor_name: string;
  amount: number;
  currency: string;
  date: string;
  community_name: string;
  message: string | null;
  status: string;
  payment_method: string;
  reference: string;
}

interface DonationReceiptProps {
  donationId: number;
}

/* ───────────────────────── Component ───────────────────────── */

export function DonationReceipt({ donationId }: DonationReceiptProps) {
  const { t, i18n } = useTranslation('volunteering');
  const [receipt, setReceipt] = useState<ReceiptData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchReceipt = async () => {
      try {
        setIsLoading(true);
        setError(null);

        const response = await api.get<ReceiptData>(`/v2/donations/${donationId}/receipt`);

        if (response.success && response.data) {
          setReceipt(response.data as unknown as ReceiptData);
        } else {
          setError(response.error || t('donations.receipt_error', 'Failed to load receipt.'));
        }
      } catch (err) {
        logError('Failed to fetch donation receipt', err);
        setError(t('donations.receipt_error', 'Failed to load receipt.'));
      } finally {
        setIsLoading(false);
      }
    };

    fetchReceipt();
  }, [donationId, t]);

  const handlePrint = () => {
    window.print();
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Spinner size="lg" />
      </div>
    );
  }

  if (error || !receipt) {
    return (
      <div className="text-center py-12">
        <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
        <p className="text-theme-muted">{error || t('donations.receipt_not_found', 'Receipt not found.')}</p>
      </div>
    );
  }

  const formattedAmount = receipt.amount.toLocaleString(undefined, {
    style: 'currency',
    currency: receipt.currency,
    minimumFractionDigits: 2,
  });

  return (
    <>
      {/* Print-specific styles */}
      <style>{`
        @media print {
          body * { visibility: hidden; }
          .donation-receipt, .donation-receipt * { visibility: visible; }
          .donation-receipt { position: absolute; left: 0; top: 0; width: 100%; }
          .no-print { display: none !important; }
        }
      `}</style>

      <Card className="donation-receipt max-w-lg mx-auto">
        <CardHeader className="flex items-center gap-2 pb-2">
          <Receipt className="w-5 h-5 text-theme-muted" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary">
            {t('donations.receipt_title', 'Donation Receipt')}
          </h2>
        </CardHeader>

        <CardBody className="space-y-4">
          {/* Reference */}
          {receipt.reference && (
            <div className="text-xs text-theme-subtle text-right">
              {t('donations.receipt_ref', 'Ref: {{ref}}', { ref: receipt.reference })}
            </div>
          )}

          {/* Amount */}
          <div className="text-center py-4 border-b border-theme-default">
            <p className="text-3xl font-bold text-theme-primary">{formattedAmount}</p>
            <Chip
              size="sm"
              color={receipt.status === 'completed' ? 'success' : 'warning'}
              variant="flat"
              className="mt-2"
            >
              {t(`donations.status.${receipt.status}`, receipt.status)}
            </Chip>
          </div>

          {/* Details */}
          <div className="space-y-3 text-sm">
            <div className="flex justify-between">
              <span className="text-theme-muted">{t('donations.receipt_donor', 'Donor')}</span>
              <span className="text-theme-primary font-medium">{receipt.donor_name}</span>
            </div>

            <div className="flex justify-between">
              <span className="text-theme-muted">{t('donations.receipt_date', 'Date')}</span>
              <span className="text-theme-primary">{new Date(receipt.date).toLocaleDateString(i18n.language)}</span>
            </div>

            <div className="flex justify-between">
              <span className="text-theme-muted">{t('donations.receipt_community', 'Community')}</span>
              <span className="text-theme-primary">{receipt.community_name}</span>
            </div>

            <div className="flex justify-between">
              <span className="text-theme-muted">{t('donations.receipt_method', 'Payment Method')}</span>
              <span className="text-theme-primary capitalize">{receipt.payment_method}</span>
            </div>

            {receipt.message && (
              <div className="pt-2 border-t border-theme-default">
                <p className="text-theme-muted text-xs mb-1">{t('donations.receipt_message', 'Message')}</p>
                <p className="text-theme-secondary italic">{receipt.message}</p>
              </div>
            )}
          </div>

          {/* Print button */}
          <div className="pt-4 no-print">
            <Button
              variant="flat"
              className="w-full"
              startContent={<Printer className="w-4 h-4" aria-hidden="true" />}
              onPress={handlePrint}
            >
              {t('donations.print_receipt', 'Print Receipt')}
            </Button>
          </div>
        </CardBody>
      </Card>
    </>
  );
}

export default DonationReceipt;
