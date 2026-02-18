/**
 * User Financial Report
 * Overview of all users with balance, earned/spent data, and transaction counts.
 * Parity: PHP Admin\TimebankingController::userReport()
 */

import { useState, useCallback, useEffect, useMemo, useRef } from 'react';
import { Link } from 'react-router-dom';
import {
  Avatar, Button, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  Input, Textarea,
} from '@heroui/react';
import { Users, ArrowLeft, Download, PlusCircle } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminTimebanking } from '../../api/adminApi';
import { DataTable, PageHeader, type Column } from '../../components';
import type { UserFinancialReport as UserFinancialReportType } from '../../api/types';

export function UserReport() {
  usePageTitle('Admin - User Financial Report');
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [users, setUsers] = useState<UserFinancialReportType[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [downloadingId, setDownloadingId] = useState<number | null>(null);

  // Balance adjustment modal
  const [adjustTarget, setAdjustTarget] = useState<UserFinancialReportType | null>(null);
  const [adjustAmount, setAdjustAmount] = useState('');
  const [adjustReason, setAdjustReason] = useState('');
  const [adjustLoading, setAdjustLoading] = useState(false);

  // Debounce search
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const loadUsers = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminTimebanking.getUserReport({
        page,
        search: search || undefined,
      });
      if (res.success && res.data) {
        const data = res.data as unknown;
        if (Array.isArray(data)) {
          setUsers(data);
          setTotal(data.length);
        } else if (data && typeof data === 'object') {
          const paginatedData = data as {
            data: UserFinancialReportType[];
            meta?: { total: number };
          };
          setUsers(paginatedData.data || []);
          setTotal(paginatedData.meta?.total || 0);
        }
      }
    } catch {
      // Silently handle
    } finally {
      setLoading(false);
    }
  }, [page, search]);

  useEffect(() => {
    loadUsers();
  }, [loadUsers]);

  const handleSearch = useCallback(
    (query: string) => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
      searchTimeoutRef.current = setTimeout(() => {
        setSearch(query);
        setPage(1);
      }, 400);
    },
    []
  );

  const handleDownloadStatement = useCallback(async (userId: number) => {
    setDownloadingId(userId);
    try {
      await adminTimebanking.downloadStatementCsv(userId);
    } catch {
      // Silently handle
    } finally {
      setDownloadingId(null);
    }
  }, []);

  const openAdjustModal = (user: UserFinancialReportType) => {
    setAdjustTarget(user);
    setAdjustAmount('');
    setAdjustReason('');
  };

  const handleAdjustBalance = async () => {
    if (!adjustTarget) return;
    const amount = parseFloat(adjustAmount);
    if (isNaN(amount) || amount === 0) {
      toast.error('Please enter a valid non-zero amount');
      return;
    }
    if (!adjustReason.trim()) {
      toast.error('A reason is required for balance adjustments');
      return;
    }
    setAdjustLoading(true);
    try {
      const res = await adminTimebanking.adjustBalance(adjustTarget.id, amount, adjustReason.trim());
      if (res.success) {
        toast.success(`Balance adjusted by ${amount > 0 ? '+' : ''}${amount}h for ${adjustTarget.name}`);
        setAdjustTarget(null);
        loadUsers();
      } else {
        toast.error('Failed to adjust balance');
      }
    } catch {
      toast.error('Failed to adjust balance');
    } finally {
      setAdjustLoading(false);
    }
  };

  const columns: Column<UserFinancialReportType>[] = useMemo(
    () => [
      {
        key: 'name',
        label: 'Name',
        sortable: true,
        render: (user) => (
          <div className="flex items-center gap-3">
            <Avatar
              src={user.avatar_url || undefined}
              name={user.name}
              size="sm"
              showFallback
            />
            <div className="min-w-0">
              <Link
                to={tenantPath(`/admin/users/${user.id}/edit`)}
                className="text-sm font-medium hover:text-primary transition-colors block truncate"
              >
                {user.name}
              </Link>
              <p className="text-xs text-default-400 truncate">{user.email}</p>
            </div>
          </div>
        ),
      },
      {
        key: 'balance',
        label: 'Balance',
        sortable: true,
        render: (user) => (
          <span className="text-sm font-semibold">
            {user.balance.toLocaleString()}h
          </span>
        ),
      },
      {
        key: 'total_earned',
        label: 'Total Earned',
        sortable: true,
        render: (user) => (
          <span className="text-sm text-success">
            +{user.total_earned.toLocaleString()}h
          </span>
        ),
      },
      {
        key: 'total_spent',
        label: 'Total Spent',
        sortable: true,
        render: (user) => (
          <span className="text-sm text-danger">
            -{user.total_spent.toLocaleString()}h
          </span>
        ),
      },
      {
        key: 'transaction_count',
        label: 'Transactions',
        sortable: true,
        render: (user) => (
          <span className="text-sm">{user.transaction_count}</span>
        ),
      },
      {
        key: 'actions' as keyof UserFinancialReportType,
        label: 'Actions',
        render: (user) => (
          <div className="flex gap-1">
            <Button
              size="sm"
              variant="flat"
              color="secondary"
              startContent={<PlusCircle size={14} />}
              onPress={() => openAdjustModal(user)}
            >
              Adjust
            </Button>
            <Button
              size="sm"
              variant="light"
              isIconOnly
              aria-label={`Download statement for ${user.name}`}
              isLoading={downloadingId === user.id}
              onPress={() => handleDownloadStatement(user.id)}
            >
              <Download size={16} />
            </Button>
          </div>
        ),
      },
    ],
    [tenantPath, downloadingId, handleDownloadStatement]
  );

  return (
    <div>
      <PageHeader
        title="User Financial Report"
        description="Overview of user balances, earnings, and spending"
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/timebanking')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            Back to Timebanking
          </Button>
        }
      />

      <DataTable<UserFinancialReportType>
        columns={columns}
        data={users}
        isLoading={loading}
        searchable
        searchPlaceholder="Search users by name..."
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        onSearch={handleSearch}
        onRefresh={loadUsers}
        emptyContent={
          <div className="flex flex-col items-center gap-2 py-8">
            <Users size={32} className="text-default-300" />
            <p className="text-sm text-default-400">No users found</p>
          </div>
        }
      />

      {adjustTarget && (
        <Modal isOpen={!!adjustTarget} onClose={() => setAdjustTarget(null)} size="md">
          <ModalContent>
            <ModalHeader>
              Adjust Balance — {adjustTarget.name}
            </ModalHeader>
            <ModalBody className="gap-4">
              <p className="text-sm text-default-500">
                Current balance: <strong>{adjustTarget.balance}h</strong>
              </p>
              <Input
                label="Amount (hours)"
                placeholder="e.g., 5 or -3"
                type="number"
                value={adjustAmount}
                onValueChange={setAdjustAmount}
                variant="bordered"
                description="Positive to credit, negative to debit"
                isRequired
              />
              <Textarea
                label="Reason"
                placeholder="Explain why this adjustment is needed..."
                value={adjustReason}
                onValueChange={setAdjustReason}
                variant="bordered"
                minRows={2}
                isRequired
              />
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" onPress={() => setAdjustTarget(null)} isDisabled={adjustLoading}>
                Cancel
              </Button>
              <Button color="primary" onPress={handleAdjustBalance} isLoading={adjustLoading}>
                Adjust Balance
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}
    </div>
  );
}

export default UserReport;
