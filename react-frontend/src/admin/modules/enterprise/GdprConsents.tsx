/**
 * GDPR Consents
 * Read-only DataTable of consent records.
 */

import { useEffect, useState, useCallback } from 'react';
import { Button, Chip } from '@heroui/react';
import { RefreshCw, CheckCircle, XCircle } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable } from '../../components';
import type { Column } from '../../components';
import type { GdprConsent } from '../../api/types';

export function GdprConsents() {
  usePageTitle('Admin - GDPR Consent Records');
  const toast = useToast();

  const [consents, setConsents] = useState<GdprConsent[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getGdprConsents();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setConsents(Array.isArray(data) ? data : []);
      }
    } catch {
      toast.error('Failed to load consent records');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const columns: Column<GdprConsent>[] = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'user_name', label: 'User', sortable: true },
    {
      key: 'consent_type',
      label: 'Type',
      sortable: true,
      render: (c) => (
        <Chip size="sm" variant="flat" color="primary" className="capitalize">
          {c.consent_type}
        </Chip>
      ),
    },
    {
      key: 'consented',
      label: 'Consented',
      render: (c) =>
        c.consented ? (
          <div className="flex items-center gap-1 text-success">
            <CheckCircle size={14} />
            <span className="text-sm">Yes</span>
          </div>
        ) : (
          <div className="flex items-center gap-1 text-danger">
            <XCircle size={14} />
            <span className="text-sm">No</span>
          </div>
        ),
    },
    {
      key: 'created_at',
      label: 'Date',
      sortable: true,
      render: (c) => new Date(c.consented_at || c.created_at).toLocaleDateString(),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Consent Records"
        description="Read-only view of user consent records"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            Refresh
          </Button>
        }
      />

      <DataTable
        columns={columns}
        data={consents}
        isLoading={loading}
        searchable={false}
        emptyContent="No consent records found"
      />
    </div>
  );
}

export default GdprConsents;
