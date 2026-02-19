// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Audit Log
 * Read-only activity log for GDPR actions.
 */

import { useEffect, useState, useCallback } from 'react';
import { Button } from '@heroui/react';
import { RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable } from '../../components';
import type { Column } from '../../components';
import type { GdprAuditEntry } from '../../api/types';

export function GdprAuditLog() {
  usePageTitle('Admin - GDPR Audit Log');
  const toast = useToast();

  const [entries, setEntries] = useState<GdprAuditEntry[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getGdprAudit();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setEntries(Array.isArray(data) ? data : []);
      }
    } catch {
      toast.error('Failed to load GDPR audit log');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const columns: Column<GdprAuditEntry>[] = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'user_name', label: 'User', sortable: true },
    { key: 'action', label: 'Action', sortable: true },
    { key: 'description', label: 'Description' },
    {
      key: 'created_at',
      label: 'Date',
      sortable: true,
      render: (e) => new Date(e.created_at).toLocaleString(),
    },
  ];

  return (
    <div>
      <PageHeader
        title="GDPR Audit Log"
        description="Read-only audit trail of all GDPR-related actions"
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
        data={entries}
        isLoading={loading}
        searchable={false}
        emptyContent="No GDPR audit entries found"
      />
    </div>
  );
}

export default GdprAuditLog;
