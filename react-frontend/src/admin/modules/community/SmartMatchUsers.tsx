// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Smart Match Users
 * View user matching results from the smart matching engine.
 * Wired to adminMatching.getApprovals() API (existing module).
 */

import { useState, useEffect, useCallback } from 'react';
import { Spinner, Chip } from '@heroui/react';
import { Users } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminMatching } from '../../api/adminApi';
import { PageHeader, DataTable, StatusBadge, EmptyState, type Column } from '../../components';
import type { MatchApproval } from '../../api/types';

export function SmartMatchUsers() {
  usePageTitle('Admin - Smart Match Users');
  const toast = useToast();

  const [data, setData] = useState<MatchApproval[]>([]);
  const [loading, setLoading] = useState(true);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminMatching.getApprovals({ status: 'all', page });
      if (res.success && res.data) {
        const result = res.data as unknown;
        if (Array.isArray(result)) {
          setData(result);
          setTotal(result.length);
        } else if (result && typeof result === 'object') {
          const pd = result as { data?: MatchApproval[]; meta?: { total: number } };
          setData(pd.data || []);
          setTotal(pd.meta?.total || 0);
        }
      }
    } catch {
      toast.error('Failed to load match results');
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const columns: Column<MatchApproval>[] = [
    {
      key: 'user_1_name',
      label: 'User 1',
      sortable: true,
      render: (item) => (
        <div>
          <p className="font-medium text-sm">{item.user_1_name}</p>
          {item.user_1_email && <p className="text-xs text-default-400">{item.user_1_email}</p>}
        </div>
      ),
    },
    {
      key: 'user_2_name',
      label: 'User 2',
      sortable: true,
      render: (item) => (
        <div>
          <p className="font-medium text-sm">{item.user_2_name}</p>
          {item.user_2_email && <p className="text-xs text-default-400">{item.user_2_email}</p>}
        </div>
      ),
    },
    {
      key: 'listing_title',
      label: 'Listing',
      render: (item) => (
        <span className="text-sm text-default-500">{item.listing_title || '--'}</span>
      ),
    },
    {
      key: 'match_score',
      label: 'Score',
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={item.match_score >= 80 ? 'success' : item.match_score >= 50 ? 'warning' : 'default'}
        >
          {Number(item.match_score).toFixed(0)}%
        </Chip>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'created_at',
      label: 'Date',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
  ];

  if (loading) {
    return (
      <div>
        <PageHeader title="Smart Match Users" description="View user-to-user and user-to-listing match results" />
        <div className="flex justify-center py-12"><Spinner size="lg" /></div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="Smart Match Users" description="View user-to-user and user-to-listing match results" />

      {data.length === 0 ? (
        <EmptyState
          icon={Users}
          title="No Match Results"
          description="Run the smart matching engine to generate user matches. Results will appear here with match scores and reasons."
        />
      ) : (
        <DataTable
          columns={columns}
          data={data}
          searchPlaceholder="Search matches..."
          onRefresh={fetchData}
          totalItems={total}
          page={page}
          pageSize={20}
          onPageChange={setPage}
        />
      )}
    </div>
  );
}

export default SmartMatchUsers;
