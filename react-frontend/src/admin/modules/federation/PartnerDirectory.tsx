// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Directory
 * Browse available communities in the federation network.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button } from '@heroui/react';
import { Globe, RefreshCw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminFederation } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '../../components';

interface DirectoryEntry {
  id: number;
  name: string;
  slug: string;
  status: string;
  member_count: number;
  created_at: string;
}

export function PartnerDirectory() {
  usePageTitle('Admin - Partner Directory');
  const [items, setItems] = useState<DirectoryEntry[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getDirectory();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setItems((payload as { data: DirectoryEntry[] }).data || []);
        }
      }
    } catch {
      setItems([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const columns: Column<DirectoryEntry>[] = [
    { key: 'name', label: 'Community', sortable: true },
    { key: 'slug', label: 'Slug' },
    {
      key: 'member_count', label: 'Members', sortable: true,
      render: (item) => <span>{(item.member_count || 0).toLocaleString()}</span>,
    },
    { key: 'status', label: 'Status', sortable: true },
  ];

  if (!loading && items.length === 0) {
    return (
      <div>
        <PageHeader title="Partner Directory" description="Browse federation communities" />
        <EmptyState icon={Globe} title="No Communities Available" description="The federation directory is empty. Ensure federation is enabled." />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Partner Directory"
        description="Browse available communities in the federation network"
        actions={<Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>Refresh</Button>}
      />
      <DataTable columns={columns} data={items} isLoading={loading} searchPlaceholder="Search communities..." onRefresh={loadData} />
    </div>
  );
}

export default PartnerDirectory;
