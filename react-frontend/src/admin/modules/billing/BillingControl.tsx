// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BillingControl
 * God-only billing dashboard — manage tenant subscriptions, custom pricing,
 * discounts, pause/resume, grace periods, and CSV export.
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
  Switch,
  Spinner,
  useDisclosure,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
} from '@heroui/react';
import CreditCard from 'lucide-react/icons/credit-card';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Leaf from 'lucide-react/icons/leaf';
import Download from 'lucide-react/icons/download';
import { usePageTitle } from '@/hooks';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface EffectivePrice {
  monthly: number;
  yearly: number;
  has_custom: boolean;
  discount_pct: number;
  nonprofit: boolean;
}

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
  // new fields
  custom_price_monthly: number | null;
  custom_price_yearly: number | null;
  discount_percentage: number;
  discount_reason: string | null;
  grace_period_ends_at: string | null;
  is_paused: boolean;
  nonprofit_verified: boolean;
  is_in_grace_period: boolean;
  grace_days_remaining: number;
  effective_price: EffectivePrice;
}

interface PlanItem {
  id: number;
  name: string;
  slug: string;
  user_limit: number | null;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatEur(amount: number): string {
  return new Intl.NumberFormat('en-IE', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function BillingControl() {
  usePageTitle("Control");
  const { user } = useAuth();
  const toast = useToast();

  const userRecord = user as Record<string, unknown> | null;
  const isGod = userRecord?.is_god === true;

  // ---- snapshot state ----
  const [snapshot, setSnapshot] = useState<TenantSnapshot[]>([]);
  const [loading, setLoading] = useState(true);

  // ---- plans state ----
  const [plans, setPlans] = useState<PlanItem[]>([]);
  const [plansLoading, setPlansLoading] = useState(false);

  // ---- assign plan modal ----
  const { isOpen: isAssignOpen, onOpen: onAssignOpen, onClose: onAssignClose } = useDisclosure();
  const [selectedTenant, setSelectedTenant] = useState<TenantSnapshot | null>(null);
  const [selectedPlanId, setSelectedPlanId] = useState<string>('');
  const [expiresAt, setExpiresAt] = useState('');
  const [notes, setNotes] = useState('');
  const [customPriceMonthly, setCustomPriceMonthly] = useState('');
  const [customPriceYearly, setCustomPriceYearly] = useState('');
  const [discountPct, setDiscountPct] = useState('0');
  const [discountReason, setDiscountReason] = useState('');
  const [nonprofitVerified, setNonprofitVerified] = useState(false);
  const [assigning, setAssigning] = useState(false);

  // ---- grace period modal ----
  const { isOpen: isGraceOpen, onOpen: onGraceOpen, onClose: onGraceClose } = useDisclosure();
  const [graceTenant, setGraceTenant] = useState<TenantSnapshot | null>(null);
  const [graceDays, setGraceDays] = useState('30');
  const [settingGrace, setSettingGrace] = useState(false);

  // ---- pause/resume ----
  const [pausingId, setPausingId] = useState<number | null>(null);

  // ---------------------------------------------------------------------------
  // Data fetching
  // ---------------------------------------------------------------------------

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
      toast.error("Failed to load");
    } finally {
      setLoading(false);
    }
  }, [toast]);


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
        const obj = data as { data?: PlanItem[] };
        setPlans(obj.data ?? []);
      }
    } catch {
      // non-critical — plans list will be empty
    } finally {
      setPlansLoading(false);
    }
  }, [plans.length]);

  // ---------------------------------------------------------------------------
  // Assign plan handlers
  // ---------------------------------------------------------------------------

  const handleOpenAssign = (tenant: TenantSnapshot) => {
    setSelectedTenant(tenant);
    setSelectedPlanId(tenant.current_plan_id ? String(tenant.current_plan_id) : '');
    setExpiresAt('');
    setNotes('');
    setCustomPriceMonthly(tenant.custom_price_monthly !== null ? String(tenant.custom_price_monthly) : '');
    setCustomPriceYearly(tenant.custom_price_yearly !== null ? String(tenant.custom_price_yearly) : '');
    setDiscountPct(String(tenant.discount_percentage ?? 0));
    setDiscountReason(tenant.discount_reason ?? '');
    setNonprofitVerified(tenant.nonprofit_verified ?? false);
    void fetchPlans();
    onAssignOpen();
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
        custom_price_monthly: customPriceMonthly !== '' ? Number(customPriceMonthly) : null,
        custom_price_yearly: customPriceYearly !== '' ? Number(customPriceYearly) : null,
        discount_percentage: Number(discountPct) || 0,
        discount_reason: discountReason || null,
        nonprofit_verified: nonprofitVerified,
      });
      toast.success("Plan Assigned");
      onAssignClose();
      void fetchSnapshot();
    } catch {
      toast.error("Failed to assign");
    } finally {
      setAssigning(false);
    }
  };

  // ---------------------------------------------------------------------------
  // Pause / Resume handlers
  // ---------------------------------------------------------------------------

  const handlePause = async (tenant: TenantSnapshot) => {
    setPausingId(tenant.tenant_id);
    try {
      const endpoint = tenant.is_paused
        ? '/api/v2/admin/super/billing/resume'
        : '/api/v2/admin/super/billing/pause';
      await api.post(endpoint, { tenant_id: tenant.tenant_id });
      toast.success(tenant.is_paused ? "Resume Billing" : "Pause Billing");
      void fetchSnapshot();
    } catch {
      toast.error("Failed to assign");
    } finally {
      setPausingId(null);
    }
  };

  // ---------------------------------------------------------------------------
  // Grace period handlers
  // ---------------------------------------------------------------------------

  const handleOpenGrace = (tenant: TenantSnapshot) => {
    setGraceTenant(tenant);
    setGraceDays('30');
    onGraceOpen();
  };

  const handleSetGrace = async () => {
    if (!graceTenant) return;
    const days = Math.max(1, Math.min(365, Number(graceDays) || 30));
    setSettingGrace(true);
    try {
      await api.post('/api/v2/admin/super/billing/grace-period', {
        tenant_id: graceTenant.tenant_id,
        days,
      });
      toast.success("Set Grace Period");
      onGraceClose();
      void fetchSnapshot();
    } catch {
      toast.error("Failed to assign");
    } finally {
      setSettingGrace(false);
    }
  };

  // ---------------------------------------------------------------------------
  // Plan chip rendering
  // ---------------------------------------------------------------------------

  type ChipColor = 'success' | 'warning' | 'danger' | 'default' | 'primary';

  const getPlanChipColor = (row: TenantSnapshot): ChipColor => {
    if (row.is_paused) return 'primary';
    if (!row.current_plan_id) return 'default';
    if (row.is_in_grace_period) return 'danger';
    if (row.is_over_limit) return 'warning';
    return 'success';
  };

  const getPlanChipLabel = (row: TenantSnapshot): string => {
    if (row.is_paused) return "Paused";
    if (!row.current_plan_name) return "No Plan";
    if (row.is_in_grace_period) return `In Grace Period`;
    if (row.is_over_limit) return "Over Limit";
    return row.current_plan_name;
  };

  const formatUserCount = (row: TenantSnapshot): string => {
    if (row.subtree_user_count !== row.own_user_count) {
      return `${row.own_user_count} (${row.subtree_user_count})`;
    }
    return String(row.own_user_count);
  };

  // ---------------------------------------------------------------------------
  // Loading state
  // ---------------------------------------------------------------------------

  if (loading) {
    return (
      <div>
        <PageHeader
          title={"Control"}
          description={"Control."}
        />
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <div>
      <PageHeader
        title={"Control"}
        description={"Control."}
        actions={
          <Button
            size="sm"
            variant="flat"
            color="default"
            startContent={<Download size={14} />}
            onPress={() => window.open('/api/v2/admin/super/billing/export', '_blank')}
          >
            {"Export CSV"}
          </Button>
        }
      />

      <Card>
        <CardHeader className="flex items-center gap-2 pb-0">
          <CreditCard size={18} className="text-primary" />
          <span className="font-semibold text-sm">{"Control"}</span>
        </CardHeader>
        <CardBody className="p-0">
          <Table aria-label={"Control"} removeWrapper>
            <TableHeader>
              <TableColumn>{"Tenant"}</TableColumn>
              <TableColumn>{"Users"}</TableColumn>
              <TableColumn>{"Current Plan"}</TableColumn>
              <TableColumn>{"Effective Price"}</TableColumn>
              <TableColumn>{"Status"}</TableColumn>
              {isGod
                ? <TableColumn>{"Actions"}</TableColumn>
                : <TableColumn>{''}</TableColumn>
              }
            </TableHeader>
            <TableBody emptyContent={"Failed to load"} items={snapshot}>
              {(row) => (
                <TableRow key={row.tenant_id}>
                  {/* Tenant name with depth indent */}
                  <TableCell>
                    <span
                      className="text-sm font-medium"
                      style={{ paddingLeft: `${row.depth * 16}px` }}
                    >
                      {row.is_over_limit && !row.is_in_grace_period && (
                        <AlertTriangle size={14} className="inline mr-1 text-warning-500" />
                      )}
                      {row.tenant_name}
                    </span>
                  </TableCell>

                  {/* Users: own + subtree total */}
                  <TableCell>
                    <span className="text-sm text-default-600">
                      {formatUserCount(row)}
                    </span>
                  </TableCell>

                  {/* Plan chip */}
                  <TableCell>
                    <Chip
                      size="sm"
                      variant="flat"
                      color={getPlanChipColor(row)}
                    >
                      {getPlanChipLabel(row)}
                    </Chip>
                  </TableCell>

                  {/* Effective price */}
                  <TableCell>
                    <div className="flex items-center gap-1 flex-wrap">
                      <span className="text-sm text-default-700">
                        {row.effective_price
                          ? `${formatEur(row.effective_price.yearly)}/yr`
                          : '—'
                        }
                      </span>
                      {row.effective_price?.has_custom || row.effective_price?.discount_pct > 0 ? (
                        <Chip size="sm" variant="flat" color="secondary" className="text-xs">
                          {"Custom Badge"}
                        </Chip>
                      ) : null}
                      {row.effective_price?.nonprofit && (
                        <Leaf size={12} className="text-success-500" aria-label={"Nonprofit Verified"} />
                      )}
                    </div>
                  </TableCell>

                  {/* Status */}
                  <TableCell>
                    <span className="text-sm text-default-600">
                      {row.is_paused
                        ? "Paused"
                        : row.is_in_grace_period
                          ? `In Grace Period`
                          : row.current_plan_id
                            ? "Ok"
                            : "No Plan"
                      }
                    </span>
                  </TableCell>

                  {/* Actions */}
                  <TableCell>
                    {isGod ? (
                      <div className="flex items-center gap-1 flex-wrap">
                        <Button
                          size="sm"
                          color="primary"
                          variant="flat"
                          onPress={() => handleOpenAssign(row)}
                        >
                          {"Assign Plan"}
                        </Button>
                        <Button
                          size="sm"
                          color={row.is_paused ? 'success' : 'warning'}
                          variant="flat"
                          isLoading={pausingId === row.tenant_id}
                          onPress={() => void handlePause(row)}
                        >
                          {row.is_paused ? "Resume Billing" : "Pause Billing"}
                        </Button>
                        <Button
                          size="sm"
                          color="secondary"
                          variant="flat"
                          onPress={() => handleOpenGrace(row)}
                        >
                          {"Set Grace Period"}
                        </Button>
                      </div>
                    ) : null}
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardBody>
      </Card>

      {/* ------------------------------------------------------------------ */}
      {/* Assign Plan Modal                                                    */}
      {/* ------------------------------------------------------------------ */}
      <Modal isOpen={isAssignOpen} onClose={onAssignClose} size="lg" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader>
            {"Assign Plan"}
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
                  label={"Select Plan"}
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
                  label={"Expiry Date"}
                  type="date"
                  value={expiresAt}
                  onValueChange={setExpiresAt}
                />

                <Input
                  label={"Notes"}
                  value={notes}
                  onValueChange={setNotes}
                />

                <Input
                  label={"Custom Price Monthly"}
                  type="number"
                  min={0}
                  value={customPriceMonthly}
                  onValueChange={setCustomPriceMonthly}
                  placeholder={
                    selectedPlanId && plans.length > 0
                      ? String(plans.find((p) => String(p.id) === selectedPlanId)?.user_limit ?? '')
                      : ''
                  }
                  startContent={<span className="text-default-400 text-sm">€</span>}
                />

                <Input
                  label={"Custom Price Yearly"}
                  type="number"
                  min={0}
                  value={customPriceYearly}
                  onValueChange={setCustomPriceYearly}
                  startContent={<span className="text-default-400 text-sm">€</span>}
                />

                <Input
                  label={"Discount Pct"}
                  type="number"
                  min={0}
                  max={100}
                  value={discountPct}
                  onValueChange={setDiscountPct}
                  endContent={<span className="text-default-400 text-sm">%</span>}
                />

                <Input
                  label={"Discount Reason"}
                  value={discountReason}
                  onValueChange={setDiscountReason}
                  placeholder="e.g. Charity verified, Early adopter"
                />

                <div className="flex items-center justify-between rounded-lg border border-default-200 px-4 py-3">
                  <div>
                    <p className="text-sm font-medium">{"Nonprofit Verified"}</p>
                    <p className="text-xs text-default-500">{"Nonprofit Verified."}</p>
                  </div>
                  <Switch
                    isSelected={nonprofitVerified}
                    onValueChange={setNonprofitVerified}
                    aria-label={"Nonprofit Verified"}
                  />
                </div>
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onAssignClose} isDisabled={assigning}>
              {"Cancel"}
            </Button>
            <Button
              color="primary"
              onPress={() => void handleAssign()}
              isLoading={assigning}
              isDisabled={!selectedPlanId || plansLoading}
            >
              {"Assign Plan"}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* ------------------------------------------------------------------ */}
      {/* Grace Period Modal                                                   */}
      {/* ------------------------------------------------------------------ */}
      <Modal isOpen={isGraceOpen} onClose={onGraceClose} size="sm">
        <ModalContent>
          <ModalHeader>
            {"Grace Period Modal"}
            {graceTenant && (
              <span className="text-default-500 text-sm font-normal ml-2">
                — {graceTenant.tenant_name}
              </span>
            )}
          </ModalHeader>
          <ModalBody>
            <div className="flex flex-col gap-3">
              <p className="text-sm text-default-600">{"Grace Period."}</p>
              <Input
                label={"Grace Days"}
                type="number"
                min={1}
                max={365}
                value={graceDays}
                onValueChange={setGraceDays}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onGraceClose} isDisabled={settingGrace}>
              {"Cancel"}
            </Button>
            <Button
              color="secondary"
              onPress={() => void handleSetGrace()}
              isLoading={settingGrace}
              isDisabled={!graceDays}
            >
              {"Set Grace Period"}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default BillingControl;
