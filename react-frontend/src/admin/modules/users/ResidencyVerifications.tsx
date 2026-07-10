// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Residency Verifications (AG43)
 *
 * Coordinator review queue for citizen residency declarations submitted
 * by members of caring-community (KISS cooperative) tenants. Admins can
 * approve a declaration or reject it with a mandatory reason; approval
 * awards the member the "Verified residency" badge.
 */

import { getFormattingLocale } from '@/lib/helpers';
import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import MapPin from 'lucide-react/icons/map-pin';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import XCircle from 'lucide-react/icons/circle-x';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { adminResidency } from '@/admin/api/adminApi';
import type { ResidencyVerification } from '@/admin/api/types';
import {
  Button,
  Card,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Tabs,
  Tab,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Textarea,
} from '@/components/ui';
import { ConfirmModal } from '../../components/ConfirmModal';

type StatusFilter = 'pending' | 'approved' | 'rejected' | 'all';

function statusColor(status: ResidencyVerification['status']): 'warning' | 'success' | 'danger' {
  if (status === 'approved') return 'success';
  if (status === 'rejected') return 'danger';
  return 'warning';
}

const formatDate = (value?: string | null) =>
  value
    ? new Date(value).toLocaleDateString(getFormattingLocale(), {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      })
    : '—';

export default function ResidencyVerifications() {
  const { t } = useTranslation('admin_users');
  usePageTitle(t('residency_admin.page_title'));
  const { success, error } = useToast();

  const [items, setItems] = useState<ResidencyVerification[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('pending');

  const [approveTarget, setApproveTarget] = useState<ResidencyVerification | null>(null);
  const [rejectTarget, setRejectTarget] = useState<ResidencyVerification | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [actionLoading, setActionLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminResidency.list(statusFilter);
      if (res.success) {
        setItems(res.data?.items || []);
      } else {
        error(res.error || t('residency_admin.load_failed'));
      }
    } catch {
      error(t('residency_admin.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [statusFilter, error, t]);

  useEffect(() => {
    load();
  }, [load]);

  const handleApprove = async () => {
    if (!approveTarget) return;
    setActionLoading(true);
    try {
      const res = await adminResidency.attest(approveTarget.id, 'approved');
      if (res.success) {
        success(t('residency_admin.approved_success'));
        setApproveTarget(null);
        load();
      } else {
        error(res.error || t('residency_admin.action_failed'));
        setApproveTarget(null);
      }
    } catch {
      error(t('residency_admin.action_failed'));
      setApproveTarget(null);
    } finally {
      setActionLoading(false);
    }
  };

  const handleReject = async () => {
    if (!rejectTarget) return;
    if (!rejectReason.trim()) {
      error(t('residency_admin.reason_required'));
      return;
    }
    setActionLoading(true);
    try {
      const res = await adminResidency.attest(rejectTarget.id, 'rejected', rejectReason.trim());
      if (res.success) {
        success(t('residency_admin.rejected_success'));
        setRejectTarget(null);
        setRejectReason('');
        load();
      } else {
        // Preserve the modal + typed reason so the admin can retry
        error(res.error || t('residency_admin.action_failed'));
      }
    } catch {
      error(t('residency_admin.action_failed'));
    } finally {
      setActionLoading(false);
    }
  };

  const closeReject = () => {
    setRejectTarget(null);
    setRejectReason('');
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold flex items-center gap-2">
            <MapPin className="w-6 h-6" aria-hidden="true" />
            {t('residency_admin.page_title')}
          </h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{t('residency_admin.page_desc')}</p>
        </div>
      </div>

      <Tabs
        aria-label={t('residency_admin.tabs_aria')}
        selectedKey={statusFilter}
        onSelectionChange={(key) => setStatusFilter(key as StatusFilter)}
        variant="underlined"
      >
        <Tab key="pending" title={t('residency_admin.filter_pending')} />
        <Tab key="approved" title={t('residency_admin.filter_approved')} />
        <Tab key="rejected" title={t('residency_admin.filter_rejected')} />
        <Tab key="all" title={t('residency_admin.filter_all')} />
      </Tabs>

      <Card className="p-4">
        <Table aria-label={t('residency_admin.table_aria')}>
          <TableHeader>
            <TableColumn>{t('residency_admin.col_member')}</TableColumn>
            <TableColumn>{t('residency_admin.col_municipality')}</TableColumn>
            <TableColumn>{t('residency_admin.col_postcode')}</TableColumn>
            <TableColumn>{t('residency_admin.col_details')}</TableColumn>
            <TableColumn>{t('residency_admin.col_status')}</TableColumn>
            <TableColumn>{t('residency_admin.col_submitted')}</TableColumn>
            <TableColumn>{t('residency_admin.col_actions')}</TableColumn>
          </TableHeader>
          <TableBody
            emptyContent={loading ? t('residency_admin.loading') : t('residency_admin.no_items')}
            items={items}
          >
            {(item) => (
              <TableRow key={item.id}>
                <TableCell>
                  <div>
                    <div className="font-medium">{item.member?.name || `#${item.user_id}`}</div>
                    {item.member?.email && <div className="text-xs text-gray-500">{item.member.email}</div>}
                  </div>
                </TableCell>
                <TableCell>{item.declared_municipality}</TableCell>
                <TableCell>{item.declared_postcode}</TableCell>
                <TableCell>
                  <div className="max-w-xs">
                    {item.declared_address && <div className="text-sm">{item.declared_address}</div>}
                    {item.evidence_note && (
                      <div className="text-xs text-gray-500 mt-1">
                        {t('residency_admin.evidence_note')}: {item.evidence_note}
                      </div>
                    )}
                    {item.status === 'rejected' && item.rejection_reason && (
                      <div className="text-xs text-danger mt-1">
                        {t('residency_admin.rejection_reason')}: {item.rejection_reason}
                      </div>
                    )}
                    {!item.declared_address && !item.evidence_note && item.status !== 'rejected' && '—'}
                  </div>
                </TableCell>
                <TableCell>
                  <Chip size="sm" color={statusColor(item.status)} variant="soft">
                    {t(`residency_admin.status_${item.status}`)}
                  </Chip>
                </TableCell>
                <TableCell>{formatDate(item.created_at)}</TableCell>
                <TableCell>
                  {item.status === 'pending' ? (
                    <div className="flex items-center gap-2">
                      <Button
                        size="sm"
                        variant="primary"
                        startContent={<CheckCircle2 className="w-3 h-3" aria-hidden="true" />}
                        onPress={() => setApproveTarget(item)}
                      >
                        {t('residency_admin.approve')}
                      </Button>
                      <Button
                        size="sm"
                        variant="danger"
                        startContent={<XCircle className="w-3 h-3" aria-hidden="true" />}
                        onPress={() => {
                          setRejectReason('');
                          setRejectTarget(item);
                        }}
                      >
                        {t('residency_admin.reject')}
                      </Button>
                    </div>
                  ) : (
                    <span className="text-xs text-gray-500">
                      {item.attested_at
                        ? t('residency_admin.attested_on', { date: formatDate(item.attested_at) })
                        : '—'}
                    </span>
                  )}
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </Card>

      {/* Approve confirm */}
      <ConfirmModal
        isOpen={approveTarget !== null}
        onClose={() => setApproveTarget(null)}
        onConfirm={handleApprove}
        title={t('residency_admin.approve_title')}
        message={t('residency_admin.approve_message', {
          name: approveTarget?.member?.name || `#${approveTarget?.user_id ?? ''}`,
          municipality: approveTarget?.declared_municipality ?? '',
        })}
        confirmLabel={t('residency_admin.approve')}
        cancelLabel={t('residency_admin.cancel')}
        confirmColor="primary"
        isLoading={actionLoading}
      />

      {/* Reject modal with mandatory reason */}
      <Modal isOpen={rejectTarget !== null} onClose={closeReject}>
        <ModalContent>
          <ModalHeader>{t('residency_admin.reject_title')}</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <p className="text-sm text-gray-600 dark:text-gray-300">
                {t('residency_admin.reject_message', {
                  name: rejectTarget?.member?.name || `#${rejectTarget?.user_id ?? ''}`,
                })}
              </p>
              <Textarea
                label={t('residency_admin.rejection_reason')}
                placeholder={t('residency_admin.rejection_reason_placeholder')}
                value={rejectReason}
                onValueChange={setRejectReason}
                isRequired
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="tertiary" onPress={closeReject} isDisabled={actionLoading}>
              {t('residency_admin.cancel')}
            </Button>
            <Button variant="danger" onPress={handleReject} isDisabled={actionLoading || !rejectReason.trim()}>
              {t('residency_admin.reject')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
