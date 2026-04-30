// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MyVereinDuesPage — AG54
 *
 * Member-facing page that lists every Verein membership-dues row across all
 * the user's Vereine, with a "Pay now" button that opens an embedded Stripe
 * payment modal.
 *
 * API:
 *   GET  /api/v2/me/verein-dues
 *   POST /api/v2/me/verein-dues/{id}/pay
 */

import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Card, CardBody, CardHeader, Chip, Spinner } from '@heroui/react';
import Receipt from 'lucide-react/icons/receipt';
import CreditCard from 'lucide-react/icons/credit-card';
import AlertCircle from 'lucide-react/icons/circle-alert';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { StripeCheckoutModal } from '@/components/marketplace/StripeCheckoutModal';

interface DuesRow {
  id: number;
  organization_id: number;
  organization_name: string | null;
  membership_year: number;
  amount_cents: number;
  currency: string;
  status: 'pending' | 'paid' | 'overdue' | 'waived' | 'refunded' | string;
  due_date: string | null;
  paid_at: string | null;
  stripe_payment_intent_id: string | null;
}

interface ListResponse {
  items: DuesRow[];
  total: number;
}

interface PayResponse {
  client_secret: string;
  payment_intent_id: string;
  public_key: string;
}

function statusColor(status: string): 'success' | 'warning' | 'danger' | 'default' {
  if (status === 'paid' || status === 'waived') return 'success';
  if (status === 'pending') return 'warning';
  if (status === 'overdue') return 'danger';
  return 'default';
}

export function MyVereinDuesPage() {
  const { t } = useTranslation('common');
  usePageTitle(t('verein_dues.my_page_title', 'My Verein membership dues'));
  const toast = useToast();

  const [rows, setRows] = useState<DuesRow[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [activePayDuesId, setActivePayDuesId] = useState<number | null>(null);
  const [clientSecret, setClientSecret] = useState<string | null>(null);
  const [payAmount, setPayAmount] = useState<number>(0);
  const [payCurrency, setPayCurrency] = useState<string>('CHF');
  const [payTitle, setPayTitle] = useState<string>('');

  const load = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const res = await api.get<ListResponse>('/v2/me/verein-dues');
      if (res.success && res.data) {
        setRows(Array.isArray(res.data.items) ? res.data.items : []);
      } else {
        setError(t('verein_dues.errors.load_failed', 'Failed to load membership dues.'));
      }
    } catch (err) {
      logError('MyVereinDuesPage load failed', err);
      setError(t('verein_dues.errors.load_failed', 'Failed to load membership dues.'));
    } finally {
      setIsLoading(false);
    }
  }, [t]);

  useEffect(() => { void load(); }, [load]);

  const onPayClick = useCallback(async (row: DuesRow) => {
    try {
      const res = await api.post<PayResponse>(`/v2/me/verein-dues/${row.id}/pay`, {});
      if (res.success && res.data?.client_secret) {
        setActivePayDuesId(row.id);
        setClientSecret(res.data.client_secret);
        setPayAmount(row.amount_cents / 100);
        setPayCurrency(row.currency);
        setPayTitle(`${row.organization_name ?? ''} — ${row.membership_year}`);
      } else {
        toast.error(t('verein_dues.errors.start_payment_failed', 'Could not start payment. Please try again.'));
      }
    } catch (err) {
      logError('MyVereinDuesPage pay failed', err);
      toast.error(t('verein_dues.errors.start_payment_failed', 'Could not start payment. Please try again.'));
    }
  }, [toast, t]);

  const onPaymentSuccess = useCallback(() => {
    toast.success(t('verein_dues.payment_success', 'Payment received. Your membership is now active.'));
    setActivePayDuesId(null);
    setClientSecret(null);
    void load();
  }, [load, toast, t]);

  const onPaymentClose = useCallback(() => {
    setActivePayDuesId(null);
    setClientSecret(null);
  }, []);

  const formatAmount = (cents: number, currency: string) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'CHF' }).format(cents / 100);

  return (
    <div className="container max-w-4xl mx-auto p-4 md:p-6 space-y-6">
      <div className="flex items-center gap-3">
        <Receipt className="w-7 h-7 text-primary" />
        <div>
          <h1 className="text-2xl md:text-3xl font-bold text-foreground">
            {t('verein_dues.my_page_heading', 'My Verein membership dues')}
          </h1>
          <p className="text-sm text-default-500">
            {t('verein_dues.my_page_subtitle', 'Pay your annual membership dues for the Vereine you belong to.')}
          </p>
        </div>
      </div>

      {isLoading && (
        <div className="flex justify-center py-10"><Spinner size="lg" color="primary" /></div>
      )}

      {error && !isLoading && (
        <div className="flex items-start gap-2 p-3 rounded-lg bg-danger-50 text-danger text-sm">
          <AlertCircle className="w-4 h-4 mt-0.5 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {!isLoading && !error && rows.length === 0 && (
        <Card><CardBody className="text-center py-10 text-default-500">
          {t('verein_dues.empty_state', 'You do not have any membership dues records yet.')}
        </CardBody></Card>
      )}

      <div className="space-y-3">
        {rows.map((row) => {
          const isPayable = row.status === 'pending' || row.status === 'overdue';
          return (
            <Card key={row.id}>
              <CardHeader className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-3">
                  <div>
                    <div className="font-semibold text-foreground">
                      {row.organization_name ?? t('verein_dues.unnamed_verein', 'Verein')}
                    </div>
                    <div className="text-xs text-default-500">
                      {t('verein_dues.year_label', 'Year')} {row.membership_year}
                    </div>
                  </div>
                </div>
                <Chip color={statusColor(row.status)} variant="flat" size="sm">
                  {t(`verein_dues.status.${row.status}`, row.status)}
                </Chip>
              </CardHeader>
              <CardBody className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <div className="text-lg font-semibold">{formatAmount(row.amount_cents, row.currency)}</div>
                  {row.due_date && row.status !== 'paid' && (
                    <div className="text-xs text-default-500">
                      {t('verein_dues.due_label', 'Due')} {row.due_date}
                    </div>
                  )}
                  {row.paid_at && (
                    <div className="text-xs text-success flex items-center gap-1">
                      <CheckCircle2 className="w-3 h-3" />
                      {t('verein_dues.paid_label', 'Paid')} {new Date(row.paid_at).toLocaleDateString()}
                    </div>
                  )}
                </div>
                {isPayable && (
                  <Button color="primary" startContent={<CreditCard className="w-4 h-4" />} onPress={() => onPayClick(row)}>
                    {t('verein_dues.cta_pay_now', 'Pay now')}
                  </Button>
                )}
              </CardBody>
            </Card>
          );
        })}
      </div>

      {activePayDuesId !== null && clientSecret && (
        <StripeCheckoutModal
          isOpen={true}
          clientSecret={clientSecret}
          amount={payAmount}
          currency={payCurrency}
          listingTitle={payTitle}
          onSuccess={onPaymentSuccess}
          onClose={onPaymentClose}
        />
      )}
    </div>
  );
}

export default MyVereinDuesPage;
