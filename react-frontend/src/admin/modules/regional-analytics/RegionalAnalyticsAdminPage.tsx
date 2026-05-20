// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG59 — Super-admin: Paid Regional Analytics subscriptions.
 *
 * Lets the platform sales team provision municipality / SME analytics
 * subscriptions, suspend / resume them, trigger ad-hoc PDF reports, and
 * inspect the per-subscription access log.
 *
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Card,
  CardBody,
  Chip,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Select,
  SelectItem,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
} from '@heroui/react';
import BarChart3 from 'lucide-react/icons/bar-chart-3';
import Download from 'lucide-react/icons/download';
import Eye from 'lucide-react/icons/eye';
import Pause from 'lucide-react/icons/pause';
import Play from 'lucide-react/icons/play';
import Plus from 'lucide-react/icons/plus';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

// ─── Types ────────────────────────────────────────────────────────────────

type PartnerType = 'municipality' | 'sme_partner';
type PlanTier = 'basic' | 'pro' | 'enterprise';
type SubStatus = 'trialing' | 'active' | 'past_due' | 'cancelled';
type Module = 'trends' | 'demand_supply' | 'demographics' | 'footfall';

interface Subscription {
  id: number;
  tenant_id: number;
  partner_name: string;
  partner_type: PartnerType;
  contact_email: string | null;
  billing_email: string | null;
  plan_tier: PlanTier;
  status: SubStatus;
  trial_ends_at: string | null;
  current_period_start: string | null;
  current_period_end: string | null;
  monthly_price_cents: number;
  currency: string;
  enabled_modules: Module[];
  created_at: string | null;
}

interface AccessLogEntry {
  id: number;
  subscription_id: number;
  tenant_id: number;
  accessed_endpoint: string;
  accessed_at: string;
  ip_hash: string | null;
  user_agent: string | null;
}

const ALL_MODULES: Module[] = ['trends', 'demand_supply', 'demographics', 'footfall'];

const statusColor = (s: SubStatus): 'success' | 'warning' | 'danger' | 'default' => {
  if (s === 'active') return 'success';
  if (s === 'trialing') return 'warning';
  if (s === 'past_due') return 'danger';
  return 'default';
};

const tierColor = (t: PlanTier): 'primary' | 'secondary' | 'warning' => {
  if (t === 'enterprise') return 'warning';
  if (t === 'pro') return 'secondary';
  return 'primary';
};

// ─── Component ────────────────────────────────────────────────────────────

export default function RegionalAnalyticsAdminPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('regional_analytics_admin.meta.title'));
  const toast = useToast();

  const [subs, setSubs] = useState<Subscription[]>([]);
  const [loading, setLoading] = useState(true);
  const [createOpen, setCreateOpen] = useState(false);
  const [logSub, setLogSub] = useState<Subscription | null>(null);
  const [log, setLog] = useState<AccessLogEntry[]>([]);
  const [logLoading, setLogLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<{ subscriptions: Subscription[] }>('/super-admin/regional-analytics/subscriptions');
      setSubs(res.data?.subscriptions ?? []);
    } catch {
      toast.error(t('regional_analytics_admin.toasts.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [t, toast]);

  useEffect(() => {
    void load();
  }, [load]);

  const updateStatus = async (id: number, status: SubStatus) => {
    try {
      await api.put(`/super-admin/regional-analytics/subscriptions/${id}`, { status });
      toast.success(t('regional_analytics_admin.toasts.status_updated', { status: t(`regional_analytics_admin.statuses.${status}`) }));
      void load();
    } catch {
      toast.error(t('regional_analytics_admin.toasts.update_failed'));
    }
  };

  const generateReport = async (id: number) => {
    try {
      await api.post(`/super-admin/regional-analytics/subscriptions/${id}/generate-report`);
      toast.success(t('regional_analytics_admin.toasts.report_queued'));
    } catch {
      toast.error(t('regional_analytics_admin.toasts.report_failed'));
    }
  };

  const openLog = async (sub: Subscription) => {
    setLogSub(sub);
    setLogLoading(true);
    try {
      const res = await api.get<{ items: AccessLogEntry[] }>(
        `/super-admin/regional-analytics/access-log?per_page=100`,
      );
      const all = res.data?.items ?? [];
      setLog(all.filter((entry) => entry.subscription_id === sub.id));
    } catch {
      setLog([]);
    } finally {
      setLogLoading(false);
    }
  };

  return (
    <div className="p-6">
      <PageHeader
        title={t('regional_analytics_admin.meta.title')}
        description={t('regional_analytics_admin.meta.description')}
        actions={
          <Button color="primary" startContent={<Plus size={16} />} onPress={() => setCreateOpen(true)}>
            {t('regional_analytics_admin.actions.new_subscription')}
          </Button>
        }
      />

      <Card shadow="sm">
        <CardBody className="p-0">
          {loading ? (
            <div className="p-10 flex justify-center">
              <Spinner />
            </div>
          ) : subs.length === 0 ? (
            <div className="p-10 text-center text-[var(--color-text-muted)]">
              {t('regional_analytics_admin.empty.subscriptions')}
            </div>
          ) : (
            <Table aria-label={t('regional_analytics_admin.tables.subscriptions_aria')} removeWrapper>
              <TableHeader>
                <TableColumn>{t('regional_analytics_admin.tables.partner')}</TableColumn>
                <TableColumn>{t('regional_analytics_admin.tables.type')}</TableColumn>
                <TableColumn>{t('regional_analytics_admin.tables.plan')}</TableColumn>
                <TableColumn>{t('regional_analytics_admin.tables.status')}</TableColumn>
                <TableColumn>{t('regional_analytics_admin.tables.period_end')}</TableColumn>
                <TableColumn>{t('regional_analytics_admin.tables.price_per_month')}</TableColumn>
                <TableColumn>{t('regional_analytics_admin.tables.modules')}</TableColumn>
                <TableColumn>{t('regional_analytics_admin.tables.actions')}</TableColumn>
              </TableHeader>
              <TableBody>
                {subs.map((s) => (
                  <TableRow key={s.id}>
                    <TableCell className="font-medium">
                      <div>{s.partner_name}</div>
                      <div className="text-xs text-[var(--color-text-muted)]">{s.contact_email}</div>
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" variant="flat">
                        {t(`regional_analytics_admin.partner_types.${s.partner_type}`)}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" color={tierColor(s.plan_tier)} variant="flat">
                        {t(`regional_analytics_admin.plan_tiers.${s.plan_tier}`)}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" color={statusColor(s.status)} variant="flat">
                        {t(`regional_analytics_admin.statuses.${s.status}`)}
                      </Chip>
                    </TableCell>
                    <TableCell className="text-xs">
                      {s.current_period_end ? s.current_period_end.slice(0, 10) : t('regional_analytics_admin.empty.value')}
                    </TableCell>
                    <TableCell>
                      {(s.monthly_price_cents / 100).toFixed(2)} {s.currency}
                    </TableCell>
                    <TableCell className="text-xs">
                      {(s.enabled_modules ?? []).map((module) => t(`regional_analytics_admin.modules.${module}`)).join(', ') ||
                        t('regional_analytics_admin.empty.value')}
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button
                          size="sm"
                          variant="light"
                          isIconOnly
                          aria-label={t('regional_analytics_admin.actions.generate_report_now')}
                          onPress={() => generateReport(s.id)}
                        >
                          <Download size={16} />
                        </Button>
                        <Button
                          size="sm"
                          variant="light"
                          isIconOnly
                          aria-label={t('regional_analytics_admin.actions.view_access_log')}
                          onPress={() => openLog(s)}
                        >
                          <Eye size={16} />
                        </Button>
                        {s.status === 'active' || s.status === 'trialing' ? (
                          <Button
                            size="sm"
                            variant="light"
                            isIconOnly
                            aria-label={t('regional_analytics_admin.actions.suspend')}
                            onPress={() => updateStatus(s.id, 'past_due')}
                          >
                            <Pause size={16} />
                          </Button>
                        ) : (
                          <Button
                            size="sm"
                            variant="light"
                            isIconOnly
                            aria-label={t('regional_analytics_admin.actions.resume')}
                            onPress={() => updateStatus(s.id, 'active')}
                          >
                            <Play size={16} />
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      <CreateSubscriptionModal
        isOpen={createOpen}
        onClose={() => setCreateOpen(false)}
        onCreated={() => {
          setCreateOpen(false);
          void load();
        }}
      />

      <Modal isOpen={logSub !== null} onClose={() => setLogSub(null)} size="3xl">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <BarChart3 size={18} /> {t('regional_analytics_admin.access_log.title', { name: logSub?.partner_name })}
          </ModalHeader>
          <ModalBody>
            {logLoading ? (
              <div className="p-6 flex justify-center">
                <Spinner />
              </div>
            ) : log.length === 0 ? (
              <div className="p-6 text-center text-[var(--color-text-muted)]">
                {t('regional_analytics_admin.empty.access_log')}
              </div>
            ) : (
              <Table aria-label={t('regional_analytics_admin.access_log.table_aria')} removeWrapper>
                <TableHeader>
                  <TableColumn>{t('regional_analytics_admin.access_log.when')}</TableColumn>
                  <TableColumn>{t('regional_analytics_admin.access_log.endpoint')}</TableColumn>
                  <TableColumn>{t('regional_analytics_admin.access_log.ip_hashed')}</TableColumn>
                  <TableColumn>{t('regional_analytics_admin.access_log.user_agent')}</TableColumn>
                </TableHeader>
                <TableBody>
                  {log.map((e) => (
                    <TableRow key={e.id}>
                      <TableCell className="text-xs">{e.accessed_at}</TableCell>
                      <TableCell className="font-mono text-xs">{e.accessed_endpoint}</TableCell>
                      <TableCell className="font-mono text-xs">
                        {e.ip_hash ? `${e.ip_hash.slice(0, 12)}...` : t('regional_analytics_admin.empty.value')}
                      </TableCell>
                      <TableCell className="text-xs">{e.user_agent ?? t('regional_analytics_admin.empty.value')}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={() => setLogSub(null)}>
              {t('regional_analytics_admin.actions.close')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

// ─── Create modal ─────────────────────────────────────────────────────────

function CreateSubscriptionModal({
  isOpen,
  onClose,
  onCreated,
}: {
  isOpen: boolean;
  onClose: () => void;
  onCreated: () => void;
}) {
  const { t } = useTranslation('admin');
  const toast = useToast();
  const [tenantId, setTenantId] = useState('');
  const [partnerName, setPartnerName] = useState('');
  const [partnerType, setPartnerType] = useState<PartnerType>('municipality');
  const [contactEmail, setContactEmail] = useState('');
  const [billingEmail, setBillingEmail] = useState('');
  const [planTier, setPlanTier] = useState<PlanTier>('basic');
  const [priceCents, setPriceCents] = useState('29900');
  const [currency, setCurrency] = useState('CHF');
  const [modules, setModules] = useState<Module[]>([...ALL_MODULES]);
  const [submitting, setSubmitting] = useState(false);

  const reset = () => {
    setTenantId('');
    setPartnerName('');
    setPartnerType('municipality');
    setContactEmail('');
    setBillingEmail('');
    setPlanTier('basic');
    setPriceCents('29900');
    setCurrency('CHF');
    setModules([...ALL_MODULES]);
  };

  const submit = async () => {
    if (!tenantId.trim() || !partnerName.trim() || !contactEmail.trim()) {
      toast.error(t('regional_analytics_admin.toasts.required_fields'));
      return;
    }
    setSubmitting(true);
    try {
      await api.post('/super-admin/regional-analytics/subscriptions', {
        tenant_id: Number(tenantId),
        partner_name: partnerName.trim(),
        partner_type: partnerType,
        contact_email: contactEmail.trim(),
        billing_email: billingEmail.trim() || null,
        plan_tier: planTier,
        monthly_price_cents: Number(priceCents) || 0,
        currency: currency.toUpperCase().slice(0, 3),
        enabled_modules: modules,
      });
      toast.success(t('regional_analytics_admin.toasts.created'));
      reset();
      onCreated();
    } catch {
      toast.error(t('regional_analytics_admin.toasts.create_failed'));
    } finally {
      setSubmitting(false);
    }
  };

  const toggleModule = (m: Module) => {
    setModules((prev) => (prev.includes(m) ? prev.filter((x) => x !== m) : [...prev, m]));
  };

  const moduleLabel = useMemo<Record<Module, string>>(
    () => ({
      trends: t('regional_analytics_admin.modules.trends'),
      demand_supply: t('regional_analytics_admin.modules.demand_supply'),
      demographics: t('regional_analytics_admin.modules.demographics'),
      footfall: t('regional_analytics_admin.modules.footfall'),
    }),
    [t],
  );

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="2xl">
      <ModalContent>
        <ModalHeader>{t('regional_analytics_admin.create_modal.title')}</ModalHeader>
        <ModalBody>
          <div className="space-y-4">
            <Input
              label={t('regional_analytics_admin.create_modal.tenant_id')}
              value={tenantId}
              onValueChange={setTenantId}
              type="number"
              isRequired
            />
            <Input
              label={t('regional_analytics_admin.create_modal.partner_name')}
              value={partnerName}
              onValueChange={setPartnerName}
              isRequired
            />
            <Select
              label={t('regional_analytics_admin.create_modal.partner_type')}
              selectedKeys={new Set([partnerType])}
              onSelectionChange={(keys) => {
                const v = Array.from(keys as Set<string>)[0] as PartnerType | undefined;
                if (v) setPartnerType(v);
              }}
            >
              <SelectItem key="municipality">{t('regional_analytics_admin.partner_types.municipality')}</SelectItem>
              <SelectItem key="sme_partner">{t('regional_analytics_admin.partner_types.sme_partner')}</SelectItem>
            </Select>
            <Input
              label={t('regional_analytics_admin.create_modal.contact_email')}
              type="email"
              value={contactEmail}
              onValueChange={setContactEmail}
              isRequired
            />
            <Input
              label={t('regional_analytics_admin.create_modal.billing_email')}
              type="email"
              value={billingEmail}
              onValueChange={setBillingEmail}
            />
            <Select
              label={t('regional_analytics_admin.create_modal.plan_tier')}
              selectedKeys={new Set([planTier])}
              onSelectionChange={(keys) => {
                const v = Array.from(keys as Set<string>)[0] as PlanTier | undefined;
                if (v) setPlanTier(v);
              }}
            >
              <SelectItem key="basic">{t('regional_analytics_admin.plan_tiers.basic')}</SelectItem>
              <SelectItem key="pro">{t('regional_analytics_admin.plan_tiers.pro')}</SelectItem>
              <SelectItem key="enterprise">{t('regional_analytics_admin.plan_tiers.enterprise')}</SelectItem>
            </Select>
            <div className="flex gap-3">
              <Input
                label={t('regional_analytics_admin.create_modal.monthly_price_cents')}
                value={priceCents}
                onValueChange={setPriceCents}
                type="number"
                className="flex-1"
              />
              <Input
                label={t('regional_analytics_admin.create_modal.currency')}
                value={currency}
                onValueChange={setCurrency}
                className="w-32"
                maxLength={3}
              />
            </div>
            <div>
              <label className="text-sm font-medium block mb-2">{t('regional_analytics_admin.create_modal.enabled_modules')}</label>
              <div className="flex flex-wrap gap-2">
                {ALL_MODULES.map((m) => (
                  <Chip
                    key={m}
                    variant={modules.includes(m) ? 'solid' : 'flat'}
                    color={modules.includes(m) ? 'primary' : 'default'}
                    onClick={() => toggleModule(m)}
                    className="cursor-pointer"
                  >
                    {moduleLabel[m]}
                  </Chip>
                ))}
              </div>
            </div>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button variant="light" onPress={onClose} isDisabled={submitting}>
            {t('regional_analytics_admin.actions.cancel')}
          </Button>
          <Button color="primary" onPress={submit} isLoading={submitting}>
            {t('regional_analytics_admin.actions.create')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
