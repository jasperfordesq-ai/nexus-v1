// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Jobs Management
 * List, search, feature/unfeature, and delete job vacancies.
 */

import { useState, useEffect, useCallback } from 'react';
import { Tabs, Tab, Chip, Button, Tooltip } from '@heroui/react';
import {
  Briefcase,
  Star,
  StarOff,
  Trash2,
  Eye,
  RefreshCw,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import {
  PageHeader,
  DataTable,
  ConfirmModal,
  EmptyState,
  type Column,
} from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Job {
  id: number;
  title: string;
  organization_name?: string;
  poster_name?: string;
  type?: string;
  applications_count: number;
  views: number;
  is_featured: boolean;
  status: string;
  deadline?: string;
  created_at: string;
}

interface JobsMeta {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const STATUS_TABS = [
  { key: 'all', label: 'All' },
  { key: 'open', label: 'Open' },
  { key: 'closed', label: 'Closed' },
  { key: 'expired', label: 'Expired' },
] as const;

const statusColorMap: Record<string, 'success' | 'default' | 'warning'> = {
  open: 'success',
  closed: 'default',
  expired: 'warning',
};

const typeLabel: Record<string, string> = {
  'full-time': 'Full-time',
  'part-time': 'Part-time',
  contract: 'Contract',
  volunteer: 'Volunteer',
  internship: 'Internship',
};

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function JobsAdmin() {
  usePageTitle('Admin - Jobs');
  const toast = useToast();
  const { tenantPath } = useTenant();

  // State
  const [items, setItems] = useState<Job[]>([]);
  const [meta, setMeta] = useState<JobsMeta>({ page: 1, per_page: 50, total: 0, total_pages: 1 });
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [confirmDelete, setConfirmDelete] = useState<Job | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  // ---------------------------------------------------------------------------
  // Data fetching
  // ---------------------------------------------------------------------------

  const loadJobs = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(page), limit: '50' });
      if (search) params.set('search', search);
      if (status !== 'all') params.set('status', status);

      const res = await api.get<{ items: Job[]; meta: JobsMeta }>(
        `/v2/admin/jobs?${params.toString()}`
      );

      if (res.success && res.data) {
        setItems(res.data.items ?? []);
        setMeta(res.data.meta ?? { page: 1, per_page: 50, total: 0, total_pages: 1 });
      }
    } catch {
      toast.error('Failed to load jobs');
    } finally {
      setLoading(false);
    }
  }, [page, status, search, toast]);

  useEffect(() => {
    loadJobs();
  }, [loadJobs]);

  // ---------------------------------------------------------------------------
  // Actions
  // ---------------------------------------------------------------------------

  const handleFeatureToggle = async (job: Job) => {
    try {
      const endpoint = job.is_featured
        ? `/v2/admin/jobs/${job.id}/unfeature`
        : `/v2/admin/jobs/${job.id}/feature`;
      const res = await api.post(endpoint);
      if (res?.success) {
        toast.success(
          job.is_featured
            ? `"${job.title}" removed from featured`
            : `"${job.title}" is now featured`
        );
        loadJobs();
      } else {
        toast.error(res?.error || 'Failed to update featured status');
      }
    } catch {
      toast.error('An unexpected error occurred');
    }
  };

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setActionLoading(true);
    try {
      const res = await api.delete(`/v2/admin/jobs/${confirmDelete.id}`);
      if (res?.success) {
        toast.success('Job deleted successfully');
        loadJobs();
      } else {
        toast.error(res?.error || 'Failed to delete job');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setActionLoading(false);
      setConfirmDelete(null);
    }
  };

  // ---------------------------------------------------------------------------
  // Columns
  // ---------------------------------------------------------------------------

  const columns: Column<Job>[] = [
    {
      key: 'title',
      label: 'Title',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.title}</span>
      ),
    },
    {
      key: 'organization_name',
      label: 'Organization / Poster',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.organization_name || item.poster_name || '--'}
        </span>
      ),
    },
    {
      key: 'type',
      label: 'Type',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">
          {typeLabel[item.type ?? ''] ?? item.type ?? '--'}
        </span>
      ),
    },
    {
      key: 'applications_count',
      label: 'Applications',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-600">{item.applications_count}</span>
      ),
    },
    {
      key: 'views',
      label: 'Views',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">{item.views}</span>
      ),
    },
    {
      key: 'is_featured',
      label: 'Featured',
      render: (item) => (
        <Tooltip content={item.is_featured ? 'Featured' : 'Not featured'}>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            onPress={() => handleFeatureToggle(item)}
            aria-label={item.is_featured ? 'Unfeature job' : 'Feature job'}
          >
            {item.is_featured ? (
              <Star size={16} className="text-warning fill-warning" />
            ) : (
              <StarOff size={16} className="text-default-400" />
            )}
          </Button>
        </Tooltip>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      sortable: true,
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={statusColorMap[item.status] || 'default'}
          className="capitalize"
        >
          {item.status}
        </Chip>
      ),
    },
    {
      key: 'deadline',
      label: 'Deadline',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.deadline ? new Date(item.deadline).toLocaleDateString() : '--'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (item) => (
        <div className="flex gap-1">
          <Tooltip content="View job">
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="primary"
              as="a"
              href={tenantPath(`/jobs/${item.id}`)}
              target="_blank"
              rel="noopener noreferrer"
              aria-label="View job"
            >
              <Eye size={14} />
            </Button>
          </Tooltip>
          <Tooltip content={item.is_featured ? 'Unfeature' : 'Feature'}>
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="warning"
              onPress={() => handleFeatureToggle(item)}
              aria-label={item.is_featured ? 'Unfeature job' : 'Feature job'}
            >
              {item.is_featured ? <StarOff size={14} /> : <Star size={14} />}
            </Button>
          </Tooltip>
          <Tooltip content="Delete">
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              color="danger"
              onPress={() => setConfirmDelete(item)}
              aria-label="Delete job"
            >
              <Trash2 size={14} />
            </Button>
          </Tooltip>
        </div>
      ),
    },
  ];

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <div>
      <PageHeader
        title="Job Vacancies"
        description="Manage job listings, featured jobs, and applications"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadJobs}
          >
            Refresh
          </Button>
        }
      />

      <div className="mb-4">
        <Tabs
          selectedKey={status}
          onSelectionChange={(key) => {
            setStatus(key as string);
            setPage(1);
          }}
          variant="underlined"
          size="sm"
        >
          {STATUS_TABS.map((tab) => (
            <Tab key={tab.key} title={tab.label} />
          ))}
        </Tabs>
      </div>

      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchPlaceholder="Search jobs by title or organization..."
        onSearch={(q) => {
          setSearch(q);
          setPage(1);
        }}
        onRefresh={loadJobs}
        totalItems={meta.total}
        page={page}
        pageSize={50}
        onPageChange={setPage}
        emptyContent={
          <EmptyState
            icon={Briefcase}
            title="No jobs found"
            description={
              search || status !== 'all'
                ? 'Try adjusting your search or filters'
                : 'No job vacancies have been posted yet'
            }
          />
        }
      />

      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title="Delete Job"
          message={`Are you sure you want to delete "${confirmDelete.title}"? This action cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={actionLoading}
        />
      )}
    </div>
  );
}

export default JobsAdmin;
