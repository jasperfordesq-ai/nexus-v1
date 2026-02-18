/**
 * Match Approvals Admin Page
 * Review and approve/reject broker match approvals.
 * Replaces the AdminPlaceholder for /admin/match-approvals.
 */

import { useState, useCallback, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  Chip,
  Tabs,
  Tab,
  Avatar,
  Progress,
  Textarea,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import {
  CheckCircle,
  XCircle,
  Clock,
  Users,
  TrendingUp,
  Eye,
  BarChart3,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminMatching } from '../../api/adminApi';
import {
  DataTable,
  PageHeader,
  StatCard,
  StatusBadge,
  type Column,
} from '../../components';
import type { MatchApproval, MatchApprovalStats } from '../../api/types';

// Score color helper
function scoreColor(score: number): 'danger' | 'warning' | 'success' {
  if (score < 50) return 'danger';
  if (score < 75) return 'warning';
  return 'success';
}

export function MatchApprovals() {
  usePageTitle('Admin - Match Approvals');
  const toast = useToast();
  const { tenantPath } = useTenant();
  const navigate = useNavigate();

  // Data state
  const [items, setItems] = useState<MatchApproval[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('pending');
  const [stats, setStats] = useState<MatchApprovalStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  // Action state
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [rejectModal, setRejectModal] = useState<{
    item: MatchApproval;
  } | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [rejectLoading, setRejectLoading] = useState(false);

  // Load approvals
  const loadItems = useCallback(async () => {
    setLoading(true);
    const res = await adminMatching.getApprovals({
      status: status === 'all' ? undefined : status,
      page,
    });
    if (res.success && res.data) {
      const data = res.data as unknown;
      if (data && typeof data === 'object' && 'data' in data) {
        const pd = data as { data: MatchApproval[]; meta?: { total: number } };
        setItems(pd.data || []);
        setTotal(pd.meta?.total || 0);
      } else if (Array.isArray(data)) {
        setItems(data);
        setTotal(data.length);
      }
    }
    setLoading(false);
  }, [page, status]);

  // Load stats
  const loadStats = useCallback(async () => {
    setStatsLoading(true);
    const res = await adminMatching.getApprovalStats(30);
    if (res.success && res.data) {
      const data = res.data as unknown;
      if (data && typeof data === 'object' && 'data' in data) {
        setStats((data as { data: MatchApprovalStats }).data);
      } else {
        setStats(data as MatchApprovalStats);
      }
    }
    setStatsLoading(false);
  }, []);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  useEffect(() => {
    loadStats();
  }, [loadStats]);

  // Quick approve
  const handleApprove = async (item: MatchApproval) => {
    setActionLoading(item.id);
    const res = await adminMatching.approveMatch(item.id);
    if (res.success) {
      toast.success(`Match #${item.id} approved`);
      loadItems();
      loadStats();
    } else {
      toast.error(res.error || 'Failed to approve match');
    }
    setActionLoading(null);
  };

  // Reject with reason
  const handleReject = async () => {
    if (!rejectModal) return;
    if (!rejectReason.trim()) {
      toast.error('Please provide a reason for rejection');
      return;
    }

    setRejectLoading(true);
    const res = await adminMatching.rejectMatch(rejectModal.item.id, rejectReason.trim());
    if (res.success) {
      toast.success(`Match #${rejectModal.item.id} rejected`);
      loadItems();
      loadStats();
    } else {
      toast.error(res.error || 'Failed to reject match');
    }
    setRejectLoading(false);
    setRejectModal(null);
    setRejectReason('');
  };

  // Table columns
  const columns: Column<MatchApproval>[] = [
    {
      key: 'match',
      label: 'Match',
      render: (item) => (
        <div className="flex items-center gap-2">
          <Avatar
            src={item.user_1_avatar || undefined}
            name={item.user_1_name}
            size="sm"
            className="shrink-0"
          />
          <div className="min-w-0">
            <p className="truncate text-sm font-medium text-foreground">
              {item.user_1_name}
            </p>
          </div>
          <span className="text-xs text-default-400 shrink-0">↔</span>
          <Avatar
            src={item.user_2_avatar || undefined}
            name={item.user_2_name}
            size="sm"
            className="shrink-0"
          />
          <div className="min-w-0">
            <p className="truncate text-sm font-medium text-foreground">
              {item.user_2_name}
            </p>
          </div>
        </div>
      ),
    },
    {
      key: 'listing_title',
      label: 'Listing',
      render: (item) =>
        item.listing_title ? (
          <span className="text-sm text-foreground">{item.listing_title}</span>
        ) : (
          <span className="text-sm text-default-400 italic">No listing</span>
        ),
    },
    {
      key: 'match_score',
      label: 'Score',
      sortable: true,
      render: (item) => (
        <div className="flex items-center gap-2 min-w-[100px]">
          <Progress
            size="sm"
            value={item.match_score}
            color={scoreColor(item.match_score)}
            className="max-w-[60px]"
            aria-label={`Match score: ${item.match_score}%`}
          />
          <Chip
            size="sm"
            variant="flat"
            color={scoreColor(item.match_score)}
          >
            {Math.round(item.match_score)}%
          </Chip>
        </div>
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
      label: 'Submitted',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {new Date(item.created_at).toLocaleDateString()}
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (item) => (
        <div className="flex gap-1">
          {item.status === 'pending' && (
            <>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="success"
                onPress={() => handleApprove(item)}
                isLoading={actionLoading === item.id}
                aria-label="Approve match"
              >
                <CheckCircle size={14} />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                color="danger"
                onPress={() => {
                  setRejectModal({ item });
                  setRejectReason('');
                }}
                aria-label="Reject match"
              >
                <XCircle size={14} />
              </Button>
            </>
          )}
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            onPress={() => navigate(tenantPath(`/admin/match-approvals/${item.id}`))}
            aria-label="View match details"
          >
            <Eye size={14} />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Match Approvals"
        description="Review and approve broker match approvals"
      />

      {/* Stats row */}
      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="Pending"
          value={stats?.pending_count ?? 0}
          icon={Clock}
          color="warning"
          loading={statsLoading}
        />
        <StatCard
          label="Approved"
          value={stats?.approved_count ?? 0}
          icon={CheckCircle}
          color="success"
          loading={statsLoading}
        />
        <StatCard
          label="Rejected"
          value={stats?.rejected_count ?? 0}
          icon={XCircle}
          color="danger"
          loading={statsLoading}
        />
        <StatCard
          label="Approval Rate"
          value={stats ? `${stats.approval_rate}%` : '0%'}
          icon={TrendingUp}
          color="primary"
          loading={statsLoading}
        />
      </div>

      {/* Status tabs */}
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
          <Tab key="pending" title={
            <div className="flex items-center gap-2">
              <Clock size={14} />
              <span>Pending</span>
              {stats && stats.pending_count > 0 && (
                <Chip size="sm" variant="flat" color="warning">
                  {stats.pending_count}
                </Chip>
              )}
            </div>
          } />
          <Tab key="approved" title={
            <div className="flex items-center gap-2">
              <CheckCircle size={14} />
              <span>Approved</span>
            </div>
          } />
          <Tab key="rejected" title={
            <div className="flex items-center gap-2">
              <XCircle size={14} />
              <span>Rejected</span>
            </div>
          } />
          <Tab key="all" title={
            <div className="flex items-center gap-2">
              <Users size={14} />
              <span>All</span>
            </div>
          } />
        </Tabs>
      </div>

      {/* Data table */}
      <DataTable
        columns={columns}
        data={items}
        isLoading={loading}
        searchable={false}
        onRefresh={() => {
          loadItems();
          loadStats();
        }}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        emptyContent={
          <div className="flex flex-col items-center gap-2 py-8">
            <BarChart3 size={40} className="text-default-300" />
            <p className="text-default-500">
              {status === 'pending'
                ? 'No pending match approvals'
                : `No ${status === 'all' ? '' : status} matches found`}
            </p>
          </div>
        }
      />

      {/* Reject modal with reason */}
      <Modal
        isOpen={!!rejectModal}
        onClose={() => {
          setRejectModal(null);
          setRejectReason('');
        }}
        size="md"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <XCircle size={20} className="text-danger" />
            Reject Match
          </ModalHeader>
          <ModalBody>
            {rejectModal && (
              <div className="mb-3">
                <p className="text-sm text-default-600">
                  Rejecting match between{' '}
                  <strong>{rejectModal.item.user_1_name}</strong> and{' '}
                  <strong>{rejectModal.item.user_2_name}</strong>.
                  The user will be notified with your reason.
                </p>
              </div>
            )}
            <Textarea
              label="Rejection reason"
              placeholder="Explain why this match is being rejected..."
              value={rejectReason}
              onValueChange={setRejectReason}
              variant="bordered"
              minRows={3}
              isRequired
            />
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => {
                setRejectModal(null);
                setRejectReason('');
              }}
              isDisabled={rejectLoading}
            >
              Cancel
            </Button>
            <Button
              color="danger"
              onPress={handleReject}
              isLoading={rejectLoading}
              isDisabled={!rejectReason.trim()}
            >
              Reject Match
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default MatchApprovals;
