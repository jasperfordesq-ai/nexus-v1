// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
import { resolveAvatarUrl } from '@/lib/helpers';
import { adminTimebanking } from '../../api/adminApi';
import { DataTable, PageHeader, type Column } from '../../components';
import type { UserFinancialReport as UserFinancialReportType } from '../../api/types';

import { useTranslation } from 'react-i18next';
export function UserReport() {
  const { t } = useTranslation('admin');
  usePageTitle(t('timebanking.page_title'));
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
      toast.error(t('timebanking.please_enter_a_valid_nonzero_amount'));
      return;
    }
    if (!adjustReason.trim()) {
      toast.error(t('timebanking.a_reason_is_required_for_balance_adjustm'));
      return;
    }
    setAdjustLoading(true);
    try {
      const res = await adminTimebanking.adjustBalance(adjustTarget.id, amount, adjustReason.trim());
      if (res.success) {
        toast.success(t('timebanking.balance_adjusted', { amount: `${amount > 0 ? '+' : ''}${amount}`, name: adjustTarget.name }));
        setAdjustTarget(null);
        loadUsers();
      } else {
        toast.error(t('timebanking.failed_to_adjust_balance'));
      }
    } catch {
      toast.error(t('timebanking.failed_to_adjust_balance'));
    } finally {
      setAdjustLoading(false);
    }
  };

  const columns: Column<UserFinancialReportType>[] = useMemo(
    () => [
      {
        key: 'name',
        label: t('timebanking.col_name'),
        sortable: true,
        render: (user) => (
          <div className="flex items-center gap-3">
            <Avatar
              src={resolveAvatarUrl(user.avatar_url) || undefined}
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
        label: t('timebanking.col_balance'),
        sortable: true,
        render: (user) => (
          <span className="text-sm font-semibold">
            {user.balance.toLocaleString()}h
          </span>
        ),
      },
      {
        key: 'total_earned',
        label: t('timebanking.col_total_earned'),
        sortable: true,
        render: (user) => (
          <span className="text-sm text-success">
            +{user.total_earned.toLocaleString()}h
          </span>
        ),
      },
      {
        key: 'total_spent',
        label: t('timebanking.col_total_spent'),
        sortable: true,
        render: (user) => (
          <span className="text-sm text-danger">
            -{user.total_spent.toLocaleString()}h
          </span>
        ),
      },
      {
        key: 'transaction_count',
        label: t('timebanking.col_transactions'),
        sortable: true,
        render: (user) => (
          <span className="text-sm">{user.transaction_count}</span>
        ),
      },
      {
        key: 'actions' as keyof UserFinancialReportType,
        label: t('timebanking.label_actions'),
        render: (user) => (
          <div className="flex gap-1">
            <Button
              size="sm"
              variant="flat"
              color="secondary"
              startContent={<PlusCircle size={14} />}
              onPress={() => openAdjustModal(user)}
            >
              {t('timebanking.adjust')}
            </Button>
            <Button
              size="sm"
              variant="light"
              isIconOnly
              aria-label={t('timebanking.download_statement_for', { name: user.name })}
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
        title={t('timebanking.user_report_title')}
        description={t('timebanking.user_report_desc')}
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/timebanking')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            {t('timebanking.back_to_timebanking')}
          </Button>
        }
      />

      <DataTable<UserFinancialReportType>
        columns={columns}
        data={users}
        isLoading={loading}
        searchable
        searchPlaceholder={t('timebanking.search_users_placeholder')}
        totalItems={total}
        page={page}
        pageSize={20}
        onPageChange={setPage}
        onSearch={handleSearch}
        onRefresh={loadUsers}
        emptyContent={
          <div className="flex flex-col items-center gap-2 py-8">
            <Users size={32} className="text-default-300" />
            <p className="text-sm text-default-400">{t('timebanking.no_users_found')}</p>
          </div>
        }
      />

      {adjustTarget && (
        <Modal isOpen={!!adjustTarget} onClose={() => setAdjustTarget(null)} size="md">
          <ModalContent>
            <ModalHeader>
              {t('timebanking.adjust_balance_for', { name: adjustTarget.name })}
            </ModalHeader>
            <ModalBody className="gap-4">
              <p className="text-sm text-default-500">
                {t('timebanking.current_balance')}: <strong>{adjustTarget.balance}h</strong>
              </p>
              <Input
                label={t('timebanking.label_amount_hours')}
                placeholder={t('timebanking.placeholder_amount_hours')}
                type="number"
                value={adjustAmount}
                onValueChange={setAdjustAmount}
                variant="bordered"
                description={t('timebanking.desc_positive_to_credit_negative_to_debit')}
                isRequired
              />
              <Textarea
                label={t('timebanking.label_reason')}
                placeholder={t('timebanking.placeholder_explain_why_this_adjustment_is_needed')}
                value={adjustReason}
                onValueChange={setAdjustReason}
                variant="bordered"
                minRows={2}
                isRequired
              />
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" onPress={() => setAdjustTarget(null)} isDisabled={adjustLoading}>
                {t('cancel')}
              </Button>
              <Button color="primary" onPress={handleAdjustBalance} isLoading={adjustLoading} isDisabled={adjustLoading}>
                {t('timebanking.adjust_balance')}
              </Button>
            </ModalFooter>
          </ModalContent>
        </Modal>
      )}
    </div>
  );
}

export default UserReport;
