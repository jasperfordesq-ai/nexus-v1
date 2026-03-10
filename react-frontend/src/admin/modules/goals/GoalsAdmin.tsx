// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Goals Management
 * View, search, and delete member goals with progress tracking.
 * API: GET  /api/v2/admin/goals
 *      DELETE /api/v2/admin/goals/{id}
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  Spinner,
  Button,
  Chip,
  Input,
  Progress,
  Pagination,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
} from '@heroui/react';
import { Target, Search, RefreshCw, Trash2, Eye } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, ConfirmModal } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Goal {
  id: number;
  title: string;
  member_name: string;
  member_id: number;
  target_value: number;
  current_value: number;
  status: 'active' | 'completed' | 'abandoned';
  has_buddy: boolean;
  created_at: string;
}

interface GoalsMeta {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

// ---------------------------------------------------------------------------
// Status color map
// ---------------------------------------------------------------------------

const statusColors: Record<string, 'primary' | 'success' | 'danger'> = {
  active: 'primary',
  completed: 'success',
  abandoned: 'danger',
};

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function GoalsAdmin() {
  usePageTitle('Admin - Goals');
  const toast = useToast();
  const { tenantPath } = useTenant();

  const [goals, setGoals] = useState<Goal[]>([]);
  const [meta, setMeta] = useState<GoalsMeta>({ page: 1, per_page: 50, total: 0, total_pages: 1 });
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');
  const [confirmDelete, setConfirmDelete] = useState<Goal | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  // -----------------------------------------------------------------------
  // Data fetching
  // -----------------------------------------------------------------------

  const loadGoals = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(page), limit: '50' });
      if (search) params.set('search', search);

      const res = await api.get(`/v2/admin/goals?${params.toString()}`);

      if (res.success && res.data) {
        const payload = res.data as { items: Goal[]; meta: GoalsMeta };
        setGoals(payload.items || []);
        setMeta(payload.meta || { page: 1, per_page: 50, total: 0, total_pages: 1 });
      }
    } catch {
      toast.error('Failed to load goals');
    } finally {
      setLoading(false);
    }
  }, [page, search, toast]);

  useEffect(() => {
    loadGoals();
  }, [loadGoals]);

  // -----------------------------------------------------------------------
  // Search handler
  // -----------------------------------------------------------------------

  const handleSearch = () => {
    setSearch(searchInput.trim());
    setPage(1);
  };

  const handleSearchKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') handleSearch();
  };

  // -----------------------------------------------------------------------
  // Delete handler
  // -----------------------------------------------------------------------

  const handleDelete = async () => {
    if (!confirmDelete) return;
    setDeleteLoading(true);
    try {
      const res = await api.delete(`/v2/admin/goals/${confirmDelete.id}`);
      if (res?.success) {
        toast.success(`Goal "${confirmDelete.title}" deleted`);
        loadGoals();
      } else {
        toast.error(res?.error || 'Failed to delete goal');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setDeleteLoading(false);
      setConfirmDelete(null);
    }
  };

  // -----------------------------------------------------------------------
  // Helpers
  // -----------------------------------------------------------------------

  const progressPercent = (goal: Goal): number => {
    if (!goal.target_value || goal.target_value <= 0) return 0;
    return Math.min(Math.round((goal.current_value / goal.target_value) * 100), 100);
  };

  const formatDate = (iso: string): string =>
    new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });

  // -----------------------------------------------------------------------
  // Render
  // -----------------------------------------------------------------------

  return (
    <div>
      <PageHeader
        title="Goals"
        description="View and manage member goals, track progress, and remove abandoned entries"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadGoals}
            isDisabled={loading}
          >
            Refresh
          </Button>
        }
      />

      {/* Search bar */}
      <div className="mb-4 flex items-center gap-2">
        <Input
          placeholder="Search goals by title or member..."
          aria-label="Search goals"
          value={searchInput}
          onValueChange={setSearchInput}
          onKeyDown={handleSearchKeyDown}
          startContent={<Search size={16} className="text-default-400" />}
          isClearable
          onClear={() => { setSearchInput(''); setSearch(''); setPage(1); }}
          className="max-w-md"
        />
        <Button color="primary" variant="flat" onPress={handleSearch}>
          Search
        </Button>
      </div>

      {/* Main table */}
      <Card>
        <CardBody className="p-0">
          {loading ? (
            <div className="flex items-center justify-center py-16">
              <Spinner size="lg" label="Loading goals..." />
            </div>
          ) : goals.length === 0 ? (
            <div className="flex flex-col items-center justify-center gap-3 py-16 text-default-400">
              <Target size={48} />
              <p className="text-lg font-medium">No goals found</p>
              <p className="text-sm">
                {search ? 'Try adjusting your search terms.' : 'No goals have been created yet.'}
              </p>
            </div>
          ) : (
            <Table
              aria-label="Goals administration table"
              removeWrapper
              isStriped
            >
              <TableHeader>
                <TableColumn>TITLE</TableColumn>
                <TableColumn>MEMBER</TableColumn>
                <TableColumn>TARGET</TableColumn>
                <TableColumn>PROGRESS</TableColumn>
                <TableColumn>STATUS</TableColumn>
                <TableColumn>BUDDY</TableColumn>
                <TableColumn>CREATED</TableColumn>
                <TableColumn>ACTIONS</TableColumn>
              </TableHeader>
              <TableBody>
                {goals.map((goal) => (
                  <TableRow key={goal.id}>
                    <TableCell>
                      <span className="font-medium text-foreground">{goal.title}</span>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm text-default-600">{goal.member_name}</span>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm text-default-600">{goal.target_value}</span>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2 min-w-[120px]">
                        <Progress
                          size="sm"
                          value={progressPercent(goal)}
                          color={goal.status === 'completed' ? 'success' : 'primary'}
                          className="flex-1"
                          aria-label={`${progressPercent(goal)}% complete`}
                        />
                        <span className="text-xs text-default-500 w-10 text-right">
                          {progressPercent(goal)}%
                        </span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={statusColors[goal.status] || 'default'}
                        className="capitalize"
                      >
                        {goal.status}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        variant="flat"
                        color={goal.has_buddy ? 'success' : 'default'}
                      >
                        {goal.has_buddy ? 'Yes' : 'No'}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm text-default-500">{formatDate(goal.created_at)}</span>
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button
                          isIconOnly
                          size="sm"
                          variant="flat"
                          color="primary"
                          aria-label="View goal"
                          onPress={() => window.open(tenantPath(`/goals/${goal.id}`), '_blank')}
                        >
                          <Eye size={14} />
                        </Button>
                        <Button
                          isIconOnly
                          size="sm"
                          variant="flat"
                          color="danger"
                          aria-label="Delete goal"
                          onPress={() => setConfirmDelete(goal)}
                        >
                          <Trash2 size={14} />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      {/* Pagination */}
      {meta.total_pages > 1 && (
        <div className="mt-4 flex items-center justify-between">
          <span className="text-sm text-default-500">
            Showing {goals.length} of {meta.total} goals
          </span>
          <Pagination
            total={meta.total_pages}
            page={page}
            onChange={setPage}
            showControls
          />
        </div>
      )}

      {/* Delete confirm modal */}
      {confirmDelete && (
        <ConfirmModal
          isOpen={!!confirmDelete}
          onClose={() => setConfirmDelete(null)}
          onConfirm={handleDelete}
          title="Delete Goal"
          message={`Are you sure you want to delete "${confirmDelete.title}"? This action cannot be undone.`}
          confirmLabel="Delete"
          confirmColor="danger"
          isLoading={deleteLoading}
        />
      )}
    </div>
  );
}

export default GoalsAdmin;
