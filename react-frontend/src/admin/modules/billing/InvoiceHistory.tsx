// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * InvoiceHistory
 * Displays invoice list with view/download actions.
 */

import { useEffect, useState, useCallback } from 'react';
import { Spinner, Chip, Button } from '@heroui/react';
import { Receipt, ExternalLink, Download } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { billingApi, type Invoice } from '../../api/billingApi';
import { PageHeader, DataTable, EmptyState, type Column } from '../../components';

function invoiceStatusColor(status: string): 'success' | 'warning' | 'danger' | 'default' {
  switch (status) {
    case 'paid':
      return 'success';
    case 'open':
      return 'warning';
    case 'void':
    case 'uncollectible':
      return 'danger';
    default:
      return 'default';
  }
}

export function InvoiceHistory() {
  const { t } = useTranslation('admin');
  usePageTitle(t('billing.view_invoices', 'Invoices'));
  const toast = useToast();

  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);

  const loadInvoices = useCallback(async () => {
    setLoading(true);
    try {
      const res = await billingApi.getInvoices();
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setInvoices(data);
        } else if (data && typeof data === 'object') {
          const pd = data as { data?: Invoice[] };
          setInvoices(pd.data || []);
        }
      }
    } catch {
      toast.error(t('billing.invoices_error', 'Failed to load invoices'));
    } finally {
      setLoading(false);
    }
  }, [toast, t]);

  useEffect(() => {
    loadInvoices();
  }, [loadInvoices]);

  const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: currency || 'EUR',
      minimumFractionDigits: 2,
    }).format(amount);
  };

  const columns: Column<Invoice>[] = [
    {
      key: 'number',
      label: t('billing.invoice_number', 'Invoice'),
      sortable: true,
      render: (item) => (
        <span className="font-medium">{item.number || item.id}</span>
      ),
    },
    {
      key: 'date',
      label: t('billing.invoice_date', 'Date'),
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.date ? new Date(item.date).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'amount',
      label: t('billing.invoice_amount', 'Amount'),
      sortable: true,
      render: (item) => (
        <span className="font-medium">{formatAmount(item.amount, item.currency)}</span>
      ),
    },
    {
      key: 'status',
      label: t('billing.invoice_status', 'Status'),
      sortable: true,
      render: (item) => (
        <Chip size="sm" variant="flat" color={invoiceStatusColor(item.status)}>
          {item.status}
        </Chip>
      ),
    },
    {
      key: 'id',
      label: t('billing.actions', 'Actions'),
      render: (item) => (
        <div className="flex gap-2">
          {item.hosted_invoice_url && (
            <Button
              size="sm"
              variant="flat"
              startContent={<ExternalLink className="w-3 h-3" />}
              onPress={() => window.open(item.hosted_invoice_url!, '_blank', 'noopener,noreferrer')}
            >
              {t('billing.invoice_view', 'View')}
            </Button>
          )}
          {item.invoice_pdf && (
            <Button
              size="sm"
              variant="flat"
              startContent={<Download className="w-3 h-3" />}
              onPress={() => window.open(item.invoice_pdf!, '_blank', 'noopener,noreferrer')}
            >
              {t('billing.invoice_download', 'PDF')}
            </Button>
          )}
        </div>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader
          title={t('billing.view_invoices', 'Invoices')}
          description={t('billing.invoices_description', 'View and download your billing invoices')}
        />
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('billing.view_invoices', 'Invoices')}
        description={t('billing.invoices_description', 'View and download your billing invoices')}
      />

      {invoices.length === 0 ? (
        <EmptyState
          icon={Receipt}
          title={t('billing.no_invoices', 'No invoices yet')}
          description={t('billing.no_invoices_desc', 'Invoices will appear here once you have an active subscription')}
        />
      ) : (
        <DataTable
          columns={columns}
          data={invoices}
          searchPlaceholder={t('billing.search_invoices', 'Search invoices...')}
          onRefresh={loadInvoices}
        />
      )}
    </div>
  );
}

export default InvoiceHistory;
