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
 * English-only by design — see project CLAUDE.md "ADMIN PANEL IS ENGLISH-ONLY".
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
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
  usePageTitle('Regional Analytics');
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
      toast.error('Failed to load subscriptions');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    void load();
  }, [load]);

  const updateStatus = async (id: number, status: SubStatus) => {
    try {
      await api.put(`/super-admin/regional-analytics/subscriptions/${id}`, { status });
      toast.success(`Subscription ${status}`);
      void load();
    } catch {
      toast.error('Update failed');
    }
  };

  const generateReport = async (id: number) => {
    try {
      await api.post(`/super-admin/regional-analytics/subscriptions/${id}/generate-report`);
      toast.success('Report generation queued');
    } catch {
      toast.error('Failed to queue report');
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
        title="Regional Analytics"
        description="Manage paid analytics subscriptions for municipality and SME partners — bucketed, privacy-safe aggregates only."
        actions={
          <Button color="primary" startContent={<Plus size={16} />} onPress={() => setCreateOpen(true)}>
            New subscription
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
              No subscriptions yet. Provision one to start selling regional analytics.
            </div>
          ) : (
            <Table aria-label="Regional analytics subscriptions" removeWrapper>
              <TableHeader>
                <TableColumn>Partner</TableColumn>
                <TableColumn>Type</TableColumn>
                <TableColumn>Plan</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Period end</TableColumn>
                <TableColumn>Price / mo</TableColumn>
                <TableColumn>Modules</TableColumn>
                <TableColumn>Actions</TableColumn>
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
                        {s.partner_type === 'municipality' ? 'Municipality' : 'SME'}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" color={tierColor(s.plan_tier)} variant="flat">
                        {s.plan_tier}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" color={statusColor(s.status)} variant="flat">
                        {s.status}
                      </Chip>
                    </TableCell>
                    <TableCell className="text-xs">
                      {s.current_period_end ? s.current_period_end.slice(0, 10) : '—'}
                    </TableCell>
                    <TableCell>
                      {(s.monthly_price_cents / 100).toFixed(2)} {s.currency}
                    </TableCell>
                    <TableCell className="text-xs">
                      {(s.enabled_modules ?? []).join(', ') || '—'}
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button
                          size="sm"
                          variant="light"
                          isIconOnly
                          aria-label="Generate report now"
                          onPress={() => generateReport(s.id)}
                        >
                          <Download size={16} />
                        </Button>
                        <Button
                          size="sm"
                          variant="light"
                          isIconOnly
                          aria-label="View access log"
                          onPress={() => openLog(s)}
                        >
                          <Eye size={16} />
                        </Button>
                        {s.status === 'active' || s.status === 'trialing' ? (
                          <Button
                            size="sm"
                            variant="light"
                            isIconOnly
                            aria-label="Suspend"
                            onPress={() => updateStatus(s.id, 'past_due')}
                          >
                            <Pause size={16} />
                          </Button>
                        ) : (
                          <Button
                            size="sm"
                            variant="light"
                            isIconOnly
                            aria-label="Resume"
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
            <BarChart3 size={18} /> Access log — {logSub?.partner_name}
          </ModalHeader>
          <ModalBody>
            {logLoading ? (
              <div className="p-6 flex justify-center">
                <Spinner />
              </div>
            ) : log.length === 0 ? (
              <div className="p-6 text-center text-[var(--color-text-muted)]">
                No access events recorded yet.
              </div>
            ) : (
              <Table aria-label="Access log" removeWrapper>
                <TableHeader>
                  <TableColumn>When</TableColumn>
                  <TableColumn>Endpoint</TableColumn>
                  <TableColumn>IP (hashed)</TableColumn>
                  <TableColumn>User agent</TableColumn>
                </TableHeader>
                <TableBody>
                  {log.map((e) => (
                    <TableRow key={e.id}>
                      <TableCell className="text-xs">{e.accessed_at}</TableCell>
                      <TableCell className="font-mono text-xs">{e.accessed_endpoint}</TableCell>
                      <TableCell className="font-mono text-xs">
                        {e.ip_hash ? e.ip_hash.slice(0, 12) + '…' : '—'}
                      </TableCell>
                      <TableCell className="text-xs">{e.user_agent ?? '—'}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={() => setLogSub(null)}>
              Close
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
      toast.error('Tenant, partner name and contact email are required');
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
      toast.success('Subscription created');
      reset();
      onCreated();
    } catch {
      toast.error('Create failed');
    } finally {
      setSubmitting(false);
    }
  };

  const toggleModule = (m: Module) => {
    setModules((prev) => (prev.includes(m) ? prev.filter((x) => x !== m) : [...prev, m]));
  };

  const moduleLabel = useMemo<Record<Module, string>>(
    () => ({
      trends: 'Trends',
      demand_supply: 'Demand & Supply',
      demographics: 'Demographics',
      footfall: 'Footfall',
    }),
    [],
  );

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="2xl">
      <ModalContent>
        <ModalHeader>New regional analytics subscription</ModalHeader>
        <ModalBody>
          <div className="space-y-4">
            <Input label="Tenant ID" value={tenantId} onValueChange={setTenantId} type="number" isRequired />
            <Input label="Partner name" value={partnerName} onValueChange={setPartnerName} isRequired />
            <Select
              label="Partner type"
              selectedKeys={new Set([partnerType])}
              onSelectionChange={(keys) => {
                const v = Array.from(keys as Set<string>)[0] as PartnerType | undefined;
                if (v) setPartnerType(v);
              }}
            >
              <SelectItem key="municipality">Municipality</SelectItem>
              <SelectItem key="sme_partner">SME partner</SelectItem>
            </Select>
            <Input label="Contact email" type="email" value={contactEmail} onValueChange={setContactEmail} isRequired />
            <Input label="Billing email" type="email" value={billingEmail} onValueChange={setBillingEmail} />
            <Select
              label="Plan tier"
              selectedKeys={new Set([planTier])}
              onSelectionChange={(keys) => {
                const v = Array.from(keys as Set<string>)[0] as PlanTier | undefined;
                if (v) setPlanTier(v);
              }}
            >
              <SelectItem key="basic">Basic</SelectItem>
              <SelectItem key="pro">Pro</SelectItem>
              <SelectItem key="enterprise">Enterprise</SelectItem>
            </Select>
            <div className="flex gap-3">
              <Input
                label="Monthly price (cents)"
                value={priceCents}
                onValueChange={setPriceCents}
                type="number"
                className="flex-1"
              />
              <Input label="Currency" value={currency} onValueChange={setCurrency} className="w-32" maxLength={3} />
            </div>
            <div>
              <label className="text-sm font-medium block mb-2">Enabled modules</label>
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
            Cancel
          </Button>
          <Button color="primary" onPress={submit} isLoading={submitting}>
            Create
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
