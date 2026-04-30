// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FadpAdminPage — AG42 Swiss FADP / nDSG Compliance Admin
 *
 * English-only admin panel page (NO t() calls — admin is English-only by design).
 * Four sections accessible via HeroUI Tabs:
 *   1. Processing Register  — table of processing activities + CSV export
 *   2. Retention Config     — per-class retention periods + data residency
 *   3. Consent Ledger       — searchable consent audit log + CSV export
 *   4. Data Residency       — summary card of declared storage location
 *
 * Route: /admin/enterprise/fadp
 */

import { useEffect, useState, useCallback } from 'react';
import {
  Card, CardBody, CardHeader,
  Button, Chip, Input, Select, SelectItem, Spinner,
  Table, TableHeader, TableColumn, TableBody, TableRow, TableCell,
  Tabs, Tab,
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  Switch,
  Textarea,
} from '@heroui/react';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Plus from 'lucide-react/icons/plus';
import Trash2 from 'lucide-react/icons/trash-2';
import Download from 'lucide-react/icons/download';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Globe from 'lucide-react/icons/globe';
import Database from 'lucide-react/icons/database';
import FileText from 'lucide-react/icons/file-text';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import api from '@/lib/api';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface ProcessingActivity {
  id: number;
  tenant_id: number;
  activity_name: string;
  purpose: string;
  data_categories: string[];
  recipients: string[] | null;
  retention_period: string;
  legal_basis: string;
  is_automated_profiling: boolean;
  is_active: boolean;
  sort_order: number;
  created_at: string;
  updated_at: string;
}

interface RetentionConfig {
  config: {
    member_data_years: number;
    transaction_data_years: number;
    activity_logs_years: number;
    messages_years: number;
    ai_embeddings_years: number;
  };
  data_residency: 'Switzerland' | 'EU' | 'International';
  dpa_contact_email: string | null;
}

interface ConsentRecord {
  id: number;
  tenant_id: number;
  user_id: number;
  consent_type: string;
  action: string;
  consent_version: string | null;
  ip_address: string | null;
  created_at: string;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function downloadCsv(rows: Record<string, unknown>[], filename: string) {
  if (rows.length === 0) return;
  const headers = Object.keys(rows[0] ?? {});
  const csvContent = [
    headers.join(','),
    ...rows.map(row =>
      headers
        .map(h => {
          const val = row[h];
          const str = Array.isArray(val) ? val.join(';') : String(val ?? '');
          return `"${str.replace(/"/g, '""')}"`;
        })
        .join(',')
    ),
  ].join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

const legalBasisColor: Record<string, 'primary' | 'success' | 'warning' | 'secondary'> = {
  consent: 'primary',
  contract: 'success',
  legal_obligation: 'warning',
  legitimate_interest: 'secondary',
};

// ---------------------------------------------------------------------------
// Empty activity form
// ---------------------------------------------------------------------------

const emptyActivity = {
  id: undefined as number | undefined,
  activity_name: '',
  purpose: '',
  data_categories: '',
  recipients: '',
  retention_period: '',
  legal_basis: 'contract',
  is_automated_profiling: false,
  sort_order: '0',
};

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

export function FadpAdminPage() {
  usePageTitle('FADP Compliance');
  const toast = useToast();

  // ---- Processing Activities ----
  const [activities, setActivities] = useState<ProcessingActivity[]>([]);
  const [activitiesLoading, setActivitiesLoading] = useState(true);
  const [activityModalOpen, setActivityModalOpen] = useState(false);
  const [activityForm, setActivityForm] = useState({ ...emptyActivity });
  const [activitySaving, setActivitySaving] = useState(false);

  // ---- Retention Config ----
  const [retention, setRetention] = useState<RetentionConfig>({
    config: {
      member_data_years: 7,
      transaction_data_years: 10,
      activity_logs_years: 3,
      messages_years: 2,
      ai_embeddings_years: 1,
    },
    data_residency: 'EU',
    dpa_contact_email: null,
  });
  const [retentionLoading, setRetentionLoading] = useState(true);
  const [retentionSaving, setRetentionSaving] = useState(false);

  // ---- Consent Ledger ----
  const [consentRecords, setConsentRecords] = useState<ConsentRecord[]>([]);
  const [consentLoading, setConsentLoading] = useState(true);
  const [registerData, setRegisterData] = useState<Record<string, unknown> | null>(null);

  // ---------------------------------------------------------------------------
  // Data loaders
  // ---------------------------------------------------------------------------

  const loadActivities = useCallback(async () => {
    setActivitiesLoading(true);
    try {
      const res = await api.get<ProcessingActivity[]>('/v2/admin/fadp/processing-activities');
      setActivities(res.data ?? []);
    } catch {
      toast.showToast('Failed to load processing activities', 'error');
    } finally {
      setActivitiesLoading(false);
    }
  }, [toast]);

  const loadRetention = useCallback(async () => {
    setRetentionLoading(true);
    try {
      const res = await api.get<RetentionConfig>('/v2/admin/fadp/retention-config');
      if (res.data) setRetention(res.data);
    } catch {
      toast.showToast('Failed to load retention config', 'error');
    } finally {
      setRetentionLoading(false);
    }
  }, [toast]);

  const loadConsentLedger = useCallback(async () => {
    setConsentLoading(true);
    try {
      const res = await api.get<ConsentRecord[]>('/v2/admin/fadp/consent-ledger');
      setConsentRecords(res.data ?? []);
    } catch {
      toast.showToast('Failed to load consent ledger', 'error');
    } finally {
      setConsentLoading(false);
    }
  }, [toast]);

  const loadRegister = useCallback(async () => {
    try {
      const res = await api.get<Record<string, unknown>>('/v2/admin/fadp/processing-register');
      setRegisterData(res.data ?? null);
    } catch {
      // non-critical
    }
  }, []);

  useEffect(() => {
    void loadActivities();
    void loadRetention();
    void loadConsentLedger();
    void loadRegister();
  }, [loadActivities, loadRetention, loadConsentLedger, loadRegister]);

  // ---------------------------------------------------------------------------
  // Processing activity handlers
  // ---------------------------------------------------------------------------

  function openAddActivity() {
    setActivityForm({ ...emptyActivity });
    setActivityModalOpen(true);
  }

  function openEditActivity(activity: ProcessingActivity) {
    setActivityForm({
      id: activity.id,
      activity_name: activity.activity_name,
      purpose: activity.purpose,
      data_categories: (activity.data_categories ?? []).join(', '),
      recipients: (activity.recipients ?? []).join(', '),
      retention_period: activity.retention_period,
      legal_basis: activity.legal_basis,
      is_automated_profiling: activity.is_automated_profiling,
      sort_order: String(activity.sort_order),
    });
    setActivityModalOpen(true);
  }

  async function saveActivity() {
    if (!activityForm.activity_name.trim() || !activityForm.purpose.trim()) {
      toast.showToast('Activity name and purpose are required', 'error');
      return;
    }
    setActivitySaving(true);
    try {
      const payload = {
        id: activityForm.id,
        activity_name: activityForm.activity_name,
        purpose: activityForm.purpose,
        data_categories: activityForm.data_categories
          .split(',')
          .map(s => s.trim())
          .filter(Boolean),
        recipients: activityForm.recipients
          ? activityForm.recipients
              .split(',')
              .map(s => s.trim())
              .filter(Boolean)
          : null,
        retention_period: activityForm.retention_period,
        legal_basis: activityForm.legal_basis,
        is_automated_profiling: activityForm.is_automated_profiling,
        sort_order: parseInt(activityForm.sort_order, 10) || 0,
      };
      await api.post('/v2/admin/fadp/processing-activities', payload);
      toast.showToast('Processing activity saved', 'success');
      setActivityModalOpen(false);
      void loadActivities();
    } catch {
      toast.showToast('Failed to save activity', 'error');
    } finally {
      setActivitySaving(false);
    }
  }

  async function deleteActivity(id: number) {
    if (!confirm('Delete this processing activity? This cannot be undone.')) return;
    try {
      await api.delete(`/v2/admin/fadp/processing-activities/${id}`);
      toast.showToast('Activity deleted', 'success');
      void loadActivities();
    } catch {
      toast.showToast('Failed to delete activity', 'error');
    }
  }

  // ---------------------------------------------------------------------------
  // Retention config handler
  // ---------------------------------------------------------------------------

  async function saveRetention() {
    setRetentionSaving(true);
    try {
      await api.put('/v2/admin/fadp/retention-config', retention);
      toast.showToast('Retention configuration saved', 'success');
      void loadRegister();
    } catch {
      toast.showToast('Failed to save retention config', 'error');
    } finally {
      setRetentionSaving(false);
    }
  }

  // ---------------------------------------------------------------------------
  // CSV export handlers
  // ---------------------------------------------------------------------------

  async function exportRegisterCsv() {
    try {
      const res = await api.get<Record<string, unknown>>('/v2/admin/fadp/processing-register');
      const reg = res.data as { processing_activities?: ProcessingActivity[] };
      const rows = (reg.processing_activities ?? []).map(a => ({
        id: a.id,
        activity_name: a.activity_name,
        purpose: a.purpose,
        data_categories: a.data_categories,
        retention_period: a.retention_period,
        legal_basis: a.legal_basis,
        is_automated_profiling: a.is_automated_profiling,
      }));
      downloadCsv(rows as Record<string, unknown>[], 'fadp-processing-register.csv');
    } catch {
      toast.showToast('Failed to export register', 'error');
    }
  }

  async function exportConsentCsv() {
    try {
      const res = await api.get<ConsentRecord[]>('/v2/admin/fadp/consent-ledger');
      downloadCsv(res.data as unknown as Record<string, unknown>[], 'fadp-consent-ledger.csv');
    } catch {
      toast.showToast('Failed to export consent ledger', 'error');
    }
  }

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text)] flex items-center gap-2">
            <ShieldCheck className="text-danger-500" size={24} />
            Swiss FADP Compliance (nDSG)
          </h1>
          <p className="text-sm text-default-500 mt-1">
            Federal Act on Data Protection — processing register, retention configuration, and consent audit
          </p>
        </div>
      </div>

      <Tabs aria-label="FADP sections" color="primary" variant="underlined">
        {/* ================================================================
            TAB 1 — Processing Register
        ================================================================ */}
        <Tab key="register" title={<span className="flex items-center gap-2"><FileText size={14} />Processing Register</span>}>
          <div className="space-y-4 pt-4">
            <div className="flex items-center justify-between gap-3 flex-wrap">
              <p className="text-sm text-default-500">
                Article 12 nDSG requires a record of all processing activities. Add all activities where personal data is processed.
              </p>
              <div className="flex gap-2">
                <Button size="sm" variant="bordered" startContent={<Download size={14} />} onPress={() => void exportRegisterCsv()}>
                  Export CSV
                </Button>
                <Button size="sm" color="primary" startContent={<Plus size={14} />} onPress={openAddActivity}>
                  Add Activity
                </Button>
              </div>
            </div>

            {activitiesLoading ? (
              <div className="flex justify-center py-12"><Spinner /></div>
            ) : (
              <Table aria-label="Processing activities" removeWrapper>
                <TableHeader>
                  <TableColumn>Activity</TableColumn>
                  <TableColumn>Legal Basis</TableColumn>
                  <TableColumn>Automated Profiling</TableColumn>
                  <TableColumn>Retention</TableColumn>
                  <TableColumn>Actions</TableColumn>
                </TableHeader>
                <TableBody emptyContent="No processing activities yet. Click 'Add Activity' to create one.">
                  {activities.map(a => (
                    <TableRow key={a.id}>
                      <TableCell>
                        <div>
                          <p className="font-medium text-sm">{a.activity_name}</p>
                          <p className="text-xs text-default-400 line-clamp-2">{a.purpose}</p>
                          {a.data_categories.length > 0 && (
                            <div className="flex flex-wrap gap-1 mt-1">
                              {a.data_categories.map(c => (
                                <Chip key={c} size="sm" variant="flat" className="text-xs">{c}</Chip>
                              ))}
                            </div>
                          )}
                        </div>
                      </TableCell>
                      <TableCell>
                        <Chip
                          size="sm"
                          color={legalBasisColor[a.legal_basis] ?? 'default'}
                          variant="flat"
                        >
                          {a.legal_basis.replace('_', ' ')}
                        </Chip>
                      </TableCell>
                      <TableCell>
                        {a.is_automated_profiling ? (
                          <Chip size="sm" color="danger" variant="flat">Yes — consent required</Chip>
                        ) : (
                          <Chip size="sm" color="default" variant="flat">No</Chip>
                        )}
                      </TableCell>
                      <TableCell>
                        <span className="text-sm text-default-600">{a.retention_period}</span>
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-2">
                          <Button
                            size="sm"
                            variant="light"
                            isIconOnly
                            onPress={() => openEditActivity(a)}
                            aria-label="Edit"
                          >
                            <RefreshCw size={14} />
                          </Button>
                          <Button
                            size="sm"
                            variant="light"
                            color="danger"
                            isIconOnly
                            onPress={() => void deleteActivity(a.id)}
                            aria-label="Delete"
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
          </div>
        </Tab>

        {/* ================================================================
            TAB 2 — Retention Config
        ================================================================ */}
        <Tab key="retention" title={<span className="flex items-center gap-2"><Database size={14} />Retention Config</span>}>
          <div className="space-y-6 pt-4 max-w-xl">
            {retentionLoading ? (
              <div className="flex justify-center py-12"><Spinner /></div>
            ) : (
              <>
                <Card>
                  <CardHeader>
                    <h3 className="text-base font-semibold">Retention Periods</h3>
                  </CardHeader>
                  <CardBody className="space-y-4">
                    {(
                      [
                        ['member_data_years', 'Member data (years)', 'Registration, profiles, authentication'],
                        ['transaction_data_years', 'Transaction data (years)', 'Time-credit exchange records'],
                        ['activity_logs_years', 'Activity logs (years)', 'Platform activity and audit logs'],
                        ['messages_years', 'Messages (years)', 'Direct and group messages'],
                        ['ai_embeddings_years', 'AI embeddings (years)', 'Matching embeddings — requires consent'],
                      ] as [keyof RetentionConfig['config'], string, string][]
                    ).map(([key, label, hint]) => (
                      <div key={key}>
                        <Input
                          type="number"
                          label={label}
                          description={hint}
                          min={1}
                          max={50}
                          value={String(retention.config[key])}
                          onValueChange={v =>
                            setRetention(r => ({
                              ...r,
                              config: { ...r.config, [key]: parseInt(v, 10) || 1 },
                            }))
                          }
                          variant="bordered"
                        />
                      </div>
                    ))}
                  </CardBody>
                </Card>

                <Card>
                  <CardHeader>
                    <h3 className="text-base font-semibold">Data Residency & DPA Contact</h3>
                  </CardHeader>
                  <CardBody className="space-y-4">
                    <Select
                      label="Data Residency Declaration"
                      selectedKeys={new Set([retention.data_residency])}
                      onSelectionChange={keys => {
                        const v = Array.from(keys)[0] as RetentionConfig['data_residency'];
                        setRetention(r => ({ ...r, data_residency: v }));
                      }}
                      variant="bordered"
                    >
                      <SelectItem key="Switzerland">Switzerland</SelectItem>
                      <SelectItem key="EU">EU (EEA)</SelectItem>
                      <SelectItem key="International">International (outside EU/Switzerland)</SelectItem>
                    </Select>
                    <Input
                      label="DPA Contact Email (optional)"
                      description="Data Protection Officer or responsible person email"
                      type="email"
                      value={retention.dpa_contact_email ?? ''}
                      onValueChange={v => setRetention(r => ({ ...r, dpa_contact_email: v || null }))}
                      variant="bordered"
                    />
                  </CardBody>
                </Card>

                <Button
                  color="primary"
                  isLoading={retentionSaving}
                  onPress={() => void saveRetention()}
                >
                  Save Configuration
                </Button>
              </>
            )}
          </div>
        </Tab>

        {/* ================================================================
            TAB 3 — Consent Ledger
        ================================================================ */}
        <Tab key="ledger" title={<span className="flex items-center gap-2"><ShieldCheck size={14} />Consent Ledger</span>}>
          <div className="space-y-4 pt-4">
            <div className="flex items-center justify-between gap-3 flex-wrap">
              <p className="text-sm text-default-500">
                Immutable audit log of all member consent decisions. Required by Art. 6 nDSG to demonstrate lawfulness.
              </p>
              <Button size="sm" variant="bordered" startContent={<Download size={14} />} onPress={() => void exportConsentCsv()}>
                Export full ledger CSV
              </Button>
            </div>

            {consentLoading ? (
              <div className="flex justify-center py-12"><Spinner /></div>
            ) : (
              <Table aria-label="Consent records" removeWrapper>
                <TableHeader>
                  <TableColumn>Member ID</TableColumn>
                  <TableColumn>Consent Type</TableColumn>
                  <TableColumn>Action</TableColumn>
                  <TableColumn>IP Address</TableColumn>
                  <TableColumn>Date</TableColumn>
                </TableHeader>
                <TableBody emptyContent="No consent records yet.">
                  {consentRecords.slice(0, 200).map(r => (
                    <TableRow key={r.id}>
                      <TableCell>
                        <span className="text-sm font-mono text-default-600">{r.user_id}</span>
                      </TableCell>
                      <TableCell>
                        <Chip size="sm" variant="flat">{r.consent_type}</Chip>
                      </TableCell>
                      <TableCell>
                        <Chip
                          size="sm"
                          color={r.action === 'granted' ? 'success' : r.action === 'withdrawn' ? 'danger' : 'warning'}
                          variant="flat"
                        >
                          {r.action}
                        </Chip>
                      </TableCell>
                      <TableCell>
                        <span className="text-xs text-default-400 font-mono">{r.ip_address ?? '—'}</span>
                      </TableCell>
                      <TableCell>
                        <span className="text-sm text-default-600">
                          {new Date(r.created_at).toLocaleString()}
                        </span>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
            {consentRecords.length > 200 && (
              <p className="text-xs text-default-400 text-center">
                Showing most recent 200 records. Use "Export full ledger CSV" to download all {consentRecords.length} records.
              </p>
            )}
          </div>
        </Tab>

        {/* ================================================================
            TAB 4 — Data Residency
        ================================================================ */}
        <Tab key="residency" title={<span className="flex items-center gap-2"><Globe size={14} />Data Residency</span>}>
          <div className="space-y-4 pt-4 max-w-2xl">
            <Card>
              <CardBody className="space-y-4 p-6">
                <div className="flex items-center gap-3">
                  <div className="w-12 h-12 rounded-full bg-primary-100 flex items-center justify-center">
                    <Globe size={22} className="text-primary-600" />
                  </div>
                  <div>
                    <h3 className="font-semibold text-[var(--color-text)]">Declared Data Residency</h3>
                    <p className="text-sm text-default-500">Where tenant member data is stored and processed</p>
                  </div>
                </div>

                <div className="flex items-center gap-3 p-4 rounded-lg bg-default-50">
                  <Chip
                    size="lg"
                    color={
                      retention.data_residency === 'Switzerland'
                        ? 'danger'
                        : retention.data_residency === 'EU'
                        ? 'primary'
                        : 'warning'
                    }
                    variant="flat"
                  >
                    {retention.data_residency}
                  </Chip>
                  <div className="text-sm text-default-600">
                    {retention.data_residency === 'Switzerland' &&
                      'Data is stored entirely within Switzerland. Maximum FADP compliance — no cross-border transfer provisions required.'}
                    {retention.data_residency === 'EU' &&
                      'Data is stored within the EU/EEA. Switzerland recognises EU data protection as adequate — no additional FADP safeguards needed for transfers.'}
                    {retention.data_residency === 'International' &&
                      'Data is stored outside Switzerland and the EU. Art. 16–17 nDSG safeguards required: standard contractual clauses or explicit member consent for each transfer.'}
                  </div>
                </div>

                {retention.dpa_contact_email && (
                  <div className="text-sm text-default-600">
                    <span className="font-medium">DPA Contact:</span>{' '}
                    <a
                      href={`mailto:${retention.dpa_contact_email}`}
                      className="text-primary hover:underline"
                    >
                      {retention.dpa_contact_email}
                    </a>
                  </div>
                )}

                <div className="border-t border-default-100 pt-4 space-y-2 text-xs text-default-500">
                  <p>
                    <span className="font-medium">FADP Art. 16:</span> Cross-border disclosure of personal data is permitted if the destination country ensures adequate data protection (recognised by the Federal Council). Switzerland, EU/EEA member states, and a small list of other countries qualify.
                  </p>
                  <p>
                    <span className="font-medium">FADP Art. 17:</span> If the destination does not qualify, appropriate safeguards must be in place (e.g., standard data protection clauses approved by the FDPIC, binding corporate rules, or member consent).
                  </p>
                  <p>
                    To update the declared residency, use the <strong>Retention Config</strong> tab.
                  </p>
                </div>
              </CardBody>
            </Card>

            {registerData && (
              <Card>
                <CardBody className="p-6 space-y-2">
                  <h4 className="font-semibold text-sm">Register Summary</h4>
                  <div className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                      <span className="text-default-500">Total activities:</span>{' '}
                      <span className="font-medium">{(registerData as { total_activities?: number }).total_activities ?? 0}</span>
                    </div>
                    <div>
                      <span className="text-default-500">Automated profiling:</span>{' '}
                      <span className="font-medium text-danger-600">
                        {(registerData as { automated_profiling_count?: number }).automated_profiling_count ?? 0}
                      </span>
                    </div>
                    <div>
                      <span className="text-default-500">Tenant:</span>{' '}
                      <span className="font-medium">{(registerData as { tenant_name?: string }).tenant_name ?? '—'}</span>
                    </div>
                    <div>
                      <span className="text-default-500">Generated:</span>{' '}
                      <span className="font-medium">
                        {new Date((registerData as { generated_at?: string }).generated_at ?? '').toLocaleString()}
                      </span>
                    </div>
                  </div>
                </CardBody>
              </Card>
            )}
          </div>
        </Tab>
      </Tabs>

      {/* ================================================================
          Activity Modal
      ================================================================ */}
      <Modal
        isOpen={activityModalOpen}
        onOpenChange={setActivityModalOpen}
        size="2xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>
                {activityForm.id ? 'Edit Processing Activity' : 'Add Processing Activity'}
              </ModalHeader>
              <ModalBody className="space-y-4">
                <Input
                  label="Activity Name"
                  isRequired
                  value={activityForm.activity_name}
                  onValueChange={v => setActivityForm(f => ({ ...f, activity_name: v }))}
                  variant="bordered"
                />
                <Textarea
                  label="Purpose"
                  isRequired
                  description="Why is this data processed? Be specific about the legitimate purpose."
                  value={activityForm.purpose}
                  onValueChange={v => setActivityForm(f => ({ ...f, purpose: v }))}
                  variant="bordered"
                  minRows={2}
                />
                <Input
                  label="Data Categories (comma-separated)"
                  description='e.g. "name, email, phone, address"'
                  value={activityForm.data_categories}
                  onValueChange={v => setActivityForm(f => ({ ...f, data_categories: v }))}
                  variant="bordered"
                />
                <Input
                  label="Recipients (comma-separated, optional)"
                  description="Who receives or accesses this data (internal teams, processors, etc.)"
                  value={activityForm.recipients}
                  onValueChange={v => setActivityForm(f => ({ ...f, recipients: v }))}
                  variant="bordered"
                />
                <Input
                  label="Retention Period"
                  description='e.g. "7 years after membership ends" or "Until consent withdrawn"'
                  value={activityForm.retention_period}
                  onValueChange={v => setActivityForm(f => ({ ...f, retention_period: v }))}
                  variant="bordered"
                />
                <Select
                  label="Legal Basis"
                  isRequired
                  selectedKeys={new Set([activityForm.legal_basis])}
                  onSelectionChange={keys => {
                    const v = Array.from(keys)[0] as string;
                    setActivityForm(f => ({ ...f, legal_basis: v }));
                  }}
                  variant="bordered"
                >
                  <SelectItem key="consent">Consent</SelectItem>
                  <SelectItem key="contract">Contract</SelectItem>
                  <SelectItem key="legal_obligation">Legal Obligation</SelectItem>
                  <SelectItem key="legitimate_interest">Legitimate Interest</SelectItem>
                </Select>
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium">Automated Profiling</p>
                    <p className="text-xs text-default-500">
                      Art. 21 nDSG: automated individual decision-making requires explicit consent and transparency.
                    </p>
                  </div>
                  <Switch
                    isSelected={activityForm.is_automated_profiling}
                    onValueChange={v => setActivityForm(f => ({ ...f, is_automated_profiling: v }))}
                    color="danger"
                  />
                </div>
                <Input
                  label="Sort Order"
                  type="number"
                  value={activityForm.sort_order}
                  onValueChange={v => setActivityForm(f => ({ ...f, sort_order: v }))}
                  variant="bordered"
                  min={0}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="light" onPress={onClose}>Cancel</Button>
                <Button
                  color="primary"
                  isLoading={activitySaving}
                  onPress={() => void saveActivity()}
                >
                  {activityForm.id ? 'Save Changes' : 'Add Activity'}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default FadpAdminPage;
