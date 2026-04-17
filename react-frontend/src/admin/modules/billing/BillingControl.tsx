// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BillingControl
 * God-only billing dashboard — manage tenant subscriptions and user-count pricing.
 * Fetches a snapshot of all tenants with their current plans and suggested plans.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Select,
  SelectItem,
  Input,
  Spinner,
  useDisclosure,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
} from '@heroui/react';
import { CreditCard, AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

interface TenantSnapshot {
  tenant_id: number;
  tenant_name: string;
  depth: number;
  own_user_count: number;
  subtree_user_count: number;
  current_plan_id: number | null;
  current_plan_name: string | null;
  current_plan_user_limit: number | null;
  suggested_plan_id: number | null;
  suggested_plan_name: string | null;
  is_over_limit: boolean;
}

interface PlanItem {
  id: number;
  name: string;
  slug: string;
  user_limit: number | null;
}

export function BillingControl() {
  const { t } = useTranslation('admin');
  usePageTitle(t('billing.control_title'));
  const { user } = useAuth();
  const toast = useToast();

  const userRecord = user as Record<string, unknown> | null;
  const isGod = userRecord?.is_god === true;

  const [snapshot, setSnapshot] = useState<TenantSnapshot[]>([]);
  const [loading, setLoading] = useState(true);
  const [plans, setPlans] = useState<PlanItem[]>([]);
  const [plansLoading, setPlansLoading] = useState(false);
  const [selectedTenant, setSelectedTenant] = useState<TenantSnapshot | null>(null);
  const [selectedPlanId, setSelectedPlanId] = useState<string>('');
  const [expiresAt, setExpiresAt] = useState('');
  const [notes, setNotes] = useState('');
  const [assigning, setAssigning] = useState(false);

  const { isOpen, onOpen, onClose } = useDisclosure();

  const fetchSnapshot = useCallback(async () => {
    setLoading(true);
    try {
      const data = await api.get('/api/v2/admin/super/billing/snapshot') as unknown;
      if (Array.isArray(data)) {
        setSnapshot(data as TenantSnapshot[]);
      } else if (data && typeof data === 'object') {
        const obj = data as { data?: TenantSnapshot[] };
        setSnapshot(obj.data ?? []);
      }
    } catch {
      toast.error(t('billing.failed_to_load'));
    } finally {
      setLoading(false);
    }
  }, [toast, t]);

  useEffect(() => {
    void fetchSnapshot();
  }, [fetchSnapshot]);

  const fetchPlans = useCallback(async () => {
    if (plans.length > 0) return;
    setPlansLoading(true);
    try {
      const data = await api.get('/api/v2/admin/plans') as unknown;
      if (Array.isArray(data)) {
        setPlans(data as PlanItem[]);
      } else if (data && typeof data === 'object') {
        const obj = data as { data?: PlanItem[]; success?: boolean };
        setPlans(obj.data ?? []);
      }
    } catch {
      // non-critical — plans list will just be empty
    } finally {
      setPlansLoading(false);
    }
  }, [plans.length]);

  const handleOpenAssign = (tenant: TenantSnapshot) => {
    setSelectedTenant(tenant);
    setSelectedPlanId(tenant.current_plan_id ? String(tenant.current_plan_id) : '');
    setExpiresAt('');
    setNotes('');
    void fetchPlans();
    onOpen();
  };

  const handleAssign = async () => {
    if (!selectedTenant || !selectedPlanId) return;
    setAssigning(true);
    try {
      await api.post('/api/v2/admin/super/billing/assign-plan', {
        tenant_id: selectedTenant.tenant_id,
        pay_plan_id: Number(selectedPlanId),
        expires_at: expiresAt || null,
        notes: notes || null,
      });
      toast.success(t('billing.plan_assigned'));
      onClose();
      void fetchSnapshot();
    } catch {
      toast.error(t('billing.failed_to_assign'));
    } finally {
      setAssigning(false);
    }
  };

  const getPlanChipColor = (row: TenantSnapshot): 'success' | 'warning' | 'default' => {
    if (!row.current_plan_id) return 'default';
    if (row.is_over_limit) return 'warning';
    return 'success';
  };

  const getPlanChipLabel = (row: TenantSnapshot): string => {
    if (!row.current_plan_name) return t('billing.no_plan');
    if (row.is_over_limit) return t('billing.over_limit');
    return t('billing.on_correct_plan');
  };

  const formatUserCount = (row: TenantSnapshot): string => {
    if (row.subtree_user_count !== row.own_user_count) {
      return `${row.own_user_count} (${row.subtree_user_count} ${t('billing.col_subtree_users')})`;
    }
    return String(row.own_user_count);
  };

  if (loading) {
    return (
      <div>
        <PageHeader
          title={t('billing.control_title')}
          description={t('billing.control_desc')}
        />
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('billing.control_title')}
        description={t('billing.control_desc')}
      />

      <Card>
        <CardHeader className="flex items-center gap-2 pb-0">
          <CreditCard size={18} className="text-primary" />
          <span className="font-semibold text-sm">{t('billing.control_title')}</span>
        </CardHeader>
        <CardBody className="p-0">
          <Table aria-label={t('billing.control_title')} removeWrapper>
            <TableHeader>
              <TableColumn>{t('billing.col_tenant')}</TableColumn>
              <TableColumn>{t('billing.col_users')}</TableColumn>
              <TableColumn>{t('billing.col_current_plan')}</TableColumn>
              <TableColumn>{t('billing.col_suggested_plan')}</TableColumn>
              {isGod ? <TableColumn>{t('billing.col_actions')}</TableColumn> : <TableColumn>{''}</TableColumn>}
            </TableHeader>
            <TableBody emptyContent={t('billing.failed_to_load')} items={snapshot}>
              {(row) => (
                <TableRow key={row.tenant_id}>
                  <TableCell>
                    <span
                      className={`text-sm font-medium pl-${Math.min(row.depth * 4, 16)}`}
                      style={{ paddingLeft: `${row.depth * 16}px` }}
                    >
                      {row.is_over_limit && (
                        <AlertTriangle size={14} className="inline mr-1 text-warning-500" />
                      )}
                      {row.tenant_name}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm text-default-600">
                      {formatUserCount(row)}
                    </span>
                  </TableCell>
                  <TableCell>
                    <div className="flex flex-col gap-1">
                      {row.current_plan_name && (
                        <span className="text-xs text-default-600">{row.current_plan_name}</span>
                      )}
                      <Chip
                        size="sm"
                        variant="flat"
                        color={getPlanChipColor(row)}
                      >
                        {getPlanChipLabel(row)}
                      </Chip>
                    </div>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm text-default-600">
                      {row.suggested_plan_name ?? '—'}
                    </span>
                  </TableCell>
                  <TableCell>
                    {isGod ? (
                      <Button
                        size="sm"
                        color="primary"
                        variant="flat"
                        onPress={() => handleOpenAssign(row)}
                      >
                        {t('billing.assign_plan')}
                      </Button>
                    ) : null}
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardBody>
      </Card>

      <Modal isOpen={isOpen} onClose={onClose} size="md">
        <ModalContent>
          <ModalHeader>
            {t('billing.assign_plan_title')}
            {selectedTenant && (
              <span className="text-default-500 text-sm font-normal ml-2">
                — {selectedTenant.tenant_name}
              </span>
            )}
          </ModalHeader>
          <ModalBody>
            {plansLoading ? (
              <div className="flex justify-center py-4">
                <Spinner size="md" />
              </div>
            ) : (
              <div className="flex flex-col gap-4">
                <Select
                  label={t('billing.select_plan')}
                  selectedKeys={selectedPlanId ? new Set([selectedPlanId]) : new Set<string>()}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0];
                    setSelectedPlanId(val ? String(val) : '');
                  }}
                  isRequired
                >
                  {plans.map((plan) => (
                    <SelectItem key={String(plan.id)}>
                      {plan.name}
                    </SelectItem>
                  ))}
                </Select>

                <Input
                  label={t('billing.expiry_date')}
                  type="date"
                  value={expiresAt}
                  onValueChange={setExpiresAt}
                />

                <Input
                  label={t('billing.notes')}
                  value={notes}
                  onValueChange={setNotes}
                />
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} isDisabled={assigning}>
              {t('advanced.cancel')}
            </Button>
            <Button
              color="primary"
              onPress={() => void handleAssign()}
              isLoading={assigning}
              isDisabled={!selectedPlanId || plansLoading}
            >
              {t('billing.assign_plan')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default BillingControl;
