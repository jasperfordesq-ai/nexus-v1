// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Consents
 * Read-only DataTable of consent records.
 */

import { useEffect, useState, useCallback } from 'react';
import { Button, Chip } from '@heroui/react';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import XCircle from 'lucide-react/icons/circle-x';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader, DataTable } from '../../components';
import type { Column } from '../../components';
import type { GdprConsent } from '../../api/types';

export function GdprConsents() {
  usePageTitle("Enterprise");
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
      toast.error("Failed to load consent records");
    } finally {
      setLoading(false);
    }
  }, [toast])


  useEffect(() => {
    loadData();
  }, [loadData]);

  const columns: Column<GdprConsent>[] = [
    { key: 'id', label: "ID", sortable: true },
    { key: 'user_name', label: "User", sortable: true },
    {
      key: 'consent_type',
      label: "Type",
      sortable: true,
      render: (c) => (
        <Chip size="sm" variant="flat" color="primary" className="capitalize">
          {c.consent_type}
        </Chip>
      ),
    },
    {
      key: 'consented',
      label: "Consented",
      render: (c) =>
        c.consented ? (
          <div className="flex items-center gap-1 text-success">
            <CheckCircle size={14} />
            <span className="text-sm">{"Yes"}</span>
          </div>
        ) : (
          <div className="flex items-center gap-1 text-danger">
            <XCircle size={14} />
            <span className="text-sm">{"No"}</span>
          </div>
        ),
    },
    {
      key: 'created_at',
      label: "Date",
      sortable: true,
      render: (c) => new Date(c.consented_at || c.created_at).toLocaleDateString(),
    },
  ];

  return (
    <div>
      <PageHeader
        title={"GDPR Consents"}
        description={"View member consent records for all legal documents"}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            {"Refresh"}
          </Button>
        }
      />

      <DataTable
        columns={columns}
        data={consents}
        isLoading={loading}
        searchable={false}
        emptyContent={"No consent records"}
      />
    </div>
  );
}

export default GdprConsents;
