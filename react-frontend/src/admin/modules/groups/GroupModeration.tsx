// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Group Moderation
 * Displays groups with reported or flagged content for moderation review.
 */

import { useState, useCallback, useEffect } from 'react';
import { Chip, Spinner } from '@heroui/react';
import { ShieldAlert, Flag } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminGroups } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, StatusBadge, type Column } from '../../components';
import type { GroupModerationItem } from '../../api/types';

export function GroupModeration() {
  usePageTitle('Admin - Group Moderation');
  const toast = useToast();

  const [items, setItems] = useState<GroupModerationItem[]>([]);
  const [loading, setLoading] = useState(true);

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminGroups.getModeration();
      if (res.success && res.data) {
        // Handle v2 envelope
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setItems(payload);
        } else if (
          payload &&
          typeof payload === 'object' &&
          'data' in (payload as Record<string, unknown>)
        ) {
          const inner = (payload as Record<string, unknown>).data;
          setItems(Array.isArray(inner) ? inner : []);
        }
      }
    } catch {
      toast.error('Failed to load moderation data');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const columns: Column<GroupModerationItem>[] = [
    {
      key: 'name',
      label: 'Group',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.name}</span>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (item) => <StatusBadge status={item.status} />,
    },
    {
      key: 'report_count',
      label: 'Reports',
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-1.5">
          {item.report_count > 0 ? (
            <Chip
              size="sm"
              variant="flat"
              color="danger"
              startContent={<Flag size={12} />}
            >
              {item.report_count} {item.report_count === 1 ? 'report' : 'reports'}
            </Chip>
          ) : (
            <span className="text-sm text-default-400">None</span>
          )}
        </div>
      ),
    },
    {
      key: 'created_at',
      label: 'Created',
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
        <PageHeader title="Content Moderation" description="Review reported and flagged group content" />
        <div className="flex items-center justify-center py-20">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="Content Moderation" description="Review reported and flagged group content" />

      {items.length === 0 ? (
        <EmptyState
          icon={ShieldAlert}
          title="No Flagged Content"
          description="There are no groups with reported or flagged content. Everything looks good."
        />
      ) : (
        <DataTable
          columns={columns}
          data={items}
          isLoading={loading}
          searchPlaceholder="Search flagged groups..."
          onRefresh={loadItems}
        />
      )}
    </div>
  );
}

export default GroupModeration;
