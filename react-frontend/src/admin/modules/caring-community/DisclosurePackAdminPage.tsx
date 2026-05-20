// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Spinner,
  Tab,
  Tabs,
  Textarea,
  Tooltip,
} from '@heroui/react';
import Download from 'lucide-react/icons/download';
import FileText from 'lucide-react/icons/file-text';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Save from 'lucide-react/icons/save';
import ShieldCheck from 'lucide-react/icons/shield-check';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { Abbr, PageHeader } from '../../components';

interface DisclosurePack {
  controller: { name: string; address: string; contact_email: string; data_protection_officer: string };
  processor: {
    name: string; address: string; contact_email: string;
    sub_processors: string[];
  };
  data_categories: Record<string, string[]>;
  lawful_basis: Record<string, string>;
  retention_defaults: Record<string, string>;
  data_subject_rights: Record<string, boolean | string>;
  federation: { enabled: boolean; aggregate_policy: string; opt_out: boolean };
  isolated_node: {
    available: boolean; description: string; hosting_owner: string;
    smtp_owner: string; storage_owner: string; backup_owner: string;
    update_cadence: string;
  };
  incident_response: {
    owner_name: string; contact_email: string;
    notification_window_hours: number; fadp_authority: string;
  };
  cross_border_transfers: {
    occurs: boolean; destinations: string[]; safeguards: string[];
  };
  amendments: { last_reviewed_at: string | null; reviewer: string; next_review_due: string | null };
}

interface PackResponse {
  pack: DisclosurePack;
  last_updated_at: string | null;
  is_customised: boolean;
}

export default function DisclosurePackAdminPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('disclosure_pack.meta.page_title'));
  const { showToast } = useToast();

  const [data, setData] = useState<PackResponse | null>(null);
  const [draft, setDraft] = useState<DisclosurePack | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [exporting, setExporting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<PackResponse>('/v2/admin/caring-community/disclosure-pack');
      setData(res.data ?? null);
      setDraft(res.data?.pack ?? null);
    } catch {
      showToast(t('disclosure_pack.toasts.load_failed'), 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast, t]);

  useEffect(() => {
    load();
  }, [load]);

  const dirty = useMemo(() => {
    if (!data || !draft) return false;
    return JSON.stringify(data.pack) !== JSON.stringify(draft);
  }, [data, draft]);

  const save = async () => {
    if (!draft) return;
    setSaving(true);
    try {
      const res = await api.put<DisclosurePack>('/v2/admin/caring-community/disclosure-pack', draft);
      const updated = res.data ?? draft;
      setDraft(updated);
      setData((prev) => (prev ? { ...prev, pack: updated, is_customised: true } : prev));
      showToast(t('disclosure_pack.toasts.saved'), 'success');
    } catch (err) {
      const msg = (err as { message?: string })?.message ?? t('disclosure_pack.toasts.save_failed');
      showToast(msg, 'error');
    } finally {
      setSaving(false);
    }
  };

  const exportMarkdown = async () => {
    setExporting(true);
    try {
      const res = await api.get<{ format: string; content: string; filename: string }>(
        '/v2/admin/caring-community/disclosure-pack/export'
      );
      const payload = res.data;
      if (!payload?.content) {
        showToast(t('disclosure_pack.toasts.export_empty'), 'error');
        return;
      }
      const blob = new Blob([payload.content], { type: 'text/markdown' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = payload.filename || 'disclosure-pack.md';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      showToast(t('disclosure_pack.toasts.exported'), 'success');
    } catch {
      showToast(t('disclosure_pack.toasts.export_failed'), 'error');
    } finally {
      setExporting(false);
    }
  };

  const setControllerField = (key: keyof DisclosurePack['controller'], value: string) => {
    if (!draft) return;
    setDraft({ ...draft, controller: { ...draft.controller, [key]: value } });
  };
  const setProcessorField = (key: keyof DisclosurePack['processor'], value: string | string[]) => {
    if (!draft) return;
    setDraft({ ...draft, processor: { ...draft.processor, [key]: value } });
  };
  const setIncidentField = <K extends keyof DisclosurePack['incident_response']>(
    key: K, value: DisclosurePack['incident_response'][K]
  ) => {
    if (!draft) return;
    setDraft({ ...draft, incident_response: { ...draft.incident_response, [key]: value } });
  };
  const setIsolatedField = <K extends keyof DisclosurePack['isolated_node']>(
    key: K, value: DisclosurePack['isolated_node'][K]
  ) => {
    if (!draft) return;
    setDraft({ ...draft, isolated_node: { ...draft.isolated_node, [key]: value } });
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('disclosure_pack.meta.title')}
        subtitle={t('disclosure_pack.meta.subtitle')}
        icon={<ShieldCheck size={20} />}
        actions={
          <div className="flex gap-2">
            <Tooltip content={t('disclosure_pack.actions.refresh')}>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                onPress={load}
                isLoading={loading}
                aria-label={t('disclosure_pack.actions.refresh_aria')}
              >
                <RefreshCw size={15} />
              </Button>
            </Tooltip>
            <Button
              size="sm"
              variant="flat"
              startContent={<Download size={14} />}
              onPress={exportMarkdown}
              isLoading={exporting}
            >
              {t('disclosure_pack.actions.export_markdown')}
            </Button>
            <Button
              size="sm"
              color="primary"
              startContent={<Save size={14} />}
              onPress={save}
              isLoading={saving}
              isDisabled={!dirty || saving}
            >
              {t('disclosure_pack.actions.save_changes')}
            </Button>
          </div>
        }
      />

      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">
                {t('disclosure_pack.about.title')}
              </p>
              <p className="text-default-600">
                {t('disclosure_pack.about.body_prefix')}{' '}
                <Abbr term="FADP" />/<Abbr term="nDSG" />{' '}
                {t('disclosure_pack.about.body_suffix')}
              </p>
              <p className="text-default-600">
                {t('disclosure_pack.about.review_prefix')}{' '}
                <Abbr term="FADP" />/<Abbr term="nDSG" />{' '}
                {t('disclosure_pack.about.review_middle')}{' '}
                <strong>{t('disclosure_pack.actions.export_markdown')}</strong>{' '}
                {t('disclosure_pack.about.review_suffix')}
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {loading && (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      )}

      {!loading && draft && (
        <Tabs aria-label={t('disclosure_pack.tabs.aria')}>
          <Tab key="controller" title={t('disclosure_pack.tabs.controller')}>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 pt-4">
              <Card>
                <CardHeader className="pb-2">
                  <span className="font-semibold text-sm">{t('disclosure_pack.sections.tenant_controller')}</span>
                </CardHeader>
                <CardBody className="pt-0 space-y-3">
                  <Input label={t('disclosure_pack.fields.controller_name')} value={draft.controller.name}
                    onValueChange={(v) => setControllerField('name', v)} />
                  <Input label={t('disclosure_pack.fields.address')} value={draft.controller.address}
                    onValueChange={(v) => setControllerField('address', v)} />
                  <Input label={t('disclosure_pack.fields.contact_email')} type="email" value={draft.controller.contact_email}
                    onValueChange={(v) => setControllerField('contact_email', v)} />
                  <Input label={t('disclosure_pack.fields.data_protection_officer')} value={draft.controller.data_protection_officer}
                    onValueChange={(v) => setControllerField('data_protection_officer', v)} />
                </CardBody>
              </Card>

              <Card>
                <CardHeader className="pb-2">
                  <span className="font-semibold text-sm">{t('disclosure_pack.sections.platform_processor')}</span>
                </CardHeader>
                <CardBody className="pt-0 space-y-3">
                  <Input label={t('disclosure_pack.fields.processor_name')} value={draft.processor.name}
                    onValueChange={(v) => setProcessorField('name', v)} />
                  <Input label={t('disclosure_pack.fields.address')} value={draft.processor.address}
                    onValueChange={(v) => setProcessorField('address', v)} />
                  <Input label={t('disclosure_pack.fields.contact_email')} type="email" value={draft.processor.contact_email}
                    onValueChange={(v) => setProcessorField('contact_email', v)} />
                  <Textarea label={t('disclosure_pack.fields.sub_processors')}
                    value={draft.processor.sub_processors.join('\n')}
                    onValueChange={(v) => setProcessorField('sub_processors', v.split('\n').map((l) => l.trim()).filter(Boolean))}
                    minRows={5}
                  />
                </CardBody>
              </Card>
            </div>
          </Tab>

          <Tab key="data" title={t('disclosure_pack.tabs.data_retention')}>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 pt-4">
              <Card>
                <CardHeader className="pb-2"><span className="font-semibold text-sm">{t('disclosure_pack.sections.data_categories')}</span></CardHeader>
                <CardBody className="pt-0 space-y-1">
                  {Object.entries(draft.data_categories).map(([cat, fields]) => (
                    <p key={cat} className="text-sm">
                      <span className="font-mono text-xs text-primary">{cat}</span>:{' '}
                      <span className="text-default-600">{(fields as string[]).join(', ')}</span>
                    </p>
                  ))}
                </CardBody>
              </Card>

              <Card>
                <CardHeader className="pb-2"><span className="font-semibold text-sm">{t('disclosure_pack.sections.lawful_basis')}</span></CardHeader>
                <CardBody className="pt-0 space-y-1">
                  {Object.entries(draft.lawful_basis).map(([cat, basis]) => (
                    <p key={cat} className="text-sm">
                      <span className="font-mono text-xs text-primary">{cat}</span>:{' '}
                      <span className="text-default-600">{basis as string}</span>
                    </p>
                  ))}
                </CardBody>
              </Card>

              <Card className="lg:col-span-2">
                <CardHeader className="pb-2"><span className="font-semibold text-sm">{t('disclosure_pack.sections.retention_defaults')}</span></CardHeader>
                <CardBody className="pt-0">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    {Object.entries(draft.retention_defaults).map(([k, v]) => (
                      <p key={k} className="text-sm">
                        <span className="font-mono text-xs text-primary">{k}</span>:{' '}
                        <span className="text-default-600">{v as string}</span>
                      </p>
                    ))}
                  </div>
                </CardBody>
              </Card>
            </div>
          </Tab>

          <Tab key="rights" title={t('disclosure_pack.tabs.data_subject_rights')}>
            <Card className="mt-4">
              <CardBody className="grid grid-cols-1 sm:grid-cols-2 gap-2 py-4">
                {Object.entries(draft.data_subject_rights).map(([k, v]) => (
                  <p key={k} className="text-sm">
                    <span className="font-mono text-xs text-primary">{k}</span>:{' '}
                    {typeof v === 'boolean' ? (
                      <Chip size="sm" color={v ? 'success' : 'default'} variant="flat">
                        {v ? t('disclosure_pack.status.enabled_lower') : t('disclosure_pack.status.disabled_lower')}
                      </Chip>
                    ) : (
                      <span className="text-default-600">{v as string}</span>
                    )}
                  </p>
                ))}
              </CardBody>
            </Card>
          </Tab>

          <Tab key="federation" title={t('disclosure_pack.tabs.federation_isolated_node')}>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 pt-4">
              <Card>
                <CardHeader className="pb-2"><span className="font-semibold text-sm">{t('disclosure_pack.sections.federation_policy')}</span></CardHeader>
                <CardBody className="pt-0 space-y-2">
                  <p className="text-sm">
                    {t('disclosure_pack.labels.aggregate_policy')}: <span className="font-mono text-xs">{draft.federation.aggregate_policy}</span>
                  </p>
                  <Chip size="sm" color={draft.federation.enabled ? 'success' : 'default'} variant="flat">
                    {draft.federation.enabled ? t('disclosure_pack.status.enabled') : t('disclosure_pack.status.disabled')}
                  </Chip>
                  <Chip size="sm" variant="flat">
                    {draft.federation.opt_out ? t('disclosure_pack.status.members_can_opt_out') : t('disclosure_pack.status.no_member_opt_out')}
                  </Chip>
                </CardBody>
              </Card>

              <Card>
                <CardHeader className="pb-2"><span className="font-semibold text-sm">{t('disclosure_pack.sections.isolated_node_configuration')}</span></CardHeader>
                <CardBody className="pt-0 space-y-3">
                  <p className="text-xs text-default-500">
                    {t('disclosure_pack.isolated_node.description_prefix')}{' '}
                    <Abbr term="FADP" />/<Abbr term="nDSG" />{' '}
                    {t('disclosure_pack.isolated_node.description_suffix')}
                  </p>
                  <Input label={t('disclosure_pack.fields.hosting_owner')} value={draft.isolated_node.hosting_owner}
                    onValueChange={(v) => setIsolatedField('hosting_owner', v)}
                    description={t('disclosure_pack.descriptions.hosting_owner')} />
                  <Input label={t('disclosure_pack.fields.smtp_owner')} value={draft.isolated_node.smtp_owner}
                    onValueChange={(v) => setIsolatedField('smtp_owner', v)}
                    description={t('disclosure_pack.descriptions.smtp_owner')} />
                  <Input label={t('disclosure_pack.fields.storage_owner')} value={draft.isolated_node.storage_owner}
                    onValueChange={(v) => setIsolatedField('storage_owner', v)}
                    description={t('disclosure_pack.descriptions.storage_owner')} />
                  <Input label={t('disclosure_pack.fields.backup_owner')} value={draft.isolated_node.backup_owner}
                    onValueChange={(v) => setIsolatedField('backup_owner', v)}
                    description={t('disclosure_pack.descriptions.backup_owner')} />
                  <Input label={t('disclosure_pack.fields.update_cadence')} value={draft.isolated_node.update_cadence}
                    onValueChange={(v) => setIsolatedField('update_cadence', v)}
                    description={t('disclosure_pack.descriptions.update_cadence')} />
                </CardBody>
              </Card>
            </div>
          </Tab>

          <Tab key="incident" title={t('disclosure_pack.tabs.incident_response')}>
            <Card className="mt-4">
              <CardBody className="space-y-3 py-4">
                <Input label={t('disclosure_pack.fields.owner_name')} value={draft.incident_response.owner_name}
                  onValueChange={(v) => setIncidentField('owner_name', v)} />
                <Input label={t('disclosure_pack.fields.contact_email')} type="email" value={draft.incident_response.contact_email}
                  onValueChange={(v) => setIncidentField('contact_email', v)} />
                <Input
                  label={t('disclosure_pack.fields.notification_window_hours')} type="number"
                  min={1} max={720}
                  value={String(draft.incident_response.notification_window_hours)}
                  onValueChange={(v) => {
                    const n = parseInt(v, 10);
                    if (!isNaN(n)) setIncidentField('notification_window_hours', n);
                  }}
                />
                <Input label={t('disclosure_pack.fields.fadp_authority')} value={draft.incident_response.fadp_authority}
                  onValueChange={(v) => setIncidentField('fadp_authority', v)} />
              </CardBody>
            </Card>
          </Tab>

          <Tab key="transfers" title={t('disclosure_pack.tabs.cross_border_transfers')}>
            <Card className="mt-4">
              <CardBody className="py-4 space-y-3">
                <p className="text-sm">
                  <Chip size="sm" color={draft.cross_border_transfers.occurs ? 'warning' : 'success'} variant="flat">
                    {draft.cross_border_transfers.occurs ? t('disclosure_pack.status.cross_border_occurs') : t('disclosure_pack.status.no_cross_border_transfers')}
                  </Chip>
                </p>
                <div>
                  <p className="text-sm font-semibold mb-1">{t('disclosure_pack.sections.destinations')}</p>
                  <ul className="list-disc pl-6 text-sm text-default-600">
                    {draft.cross_border_transfers.destinations.map((d) => <li key={d}>{d}</li>)}
                  </ul>
                </div>
                <div>
                  <p className="text-sm font-semibold mb-1">{t('disclosure_pack.sections.safeguards')}</p>
                  <ul className="list-disc pl-6 text-sm text-default-600">
                    {draft.cross_border_transfers.safeguards.map((s) => <li key={s}>{s}</li>)}
                  </ul>
                </div>
              </CardBody>
            </Card>
          </Tab>
        </Tabs>
      )}

      {!loading && data?.last_updated_at && (
        <>
          <Divider />
          <p className="text-xs text-default-500 flex items-center gap-2">
            <FileText size={12} />
            {t('disclosure_pack.footer.last_saved', { date: new Date(data.last_updated_at).toLocaleString() })}
            {data.is_customised && (
              <Chip size="sm" variant="flat" color="primary">{t('disclosure_pack.footer.customised')}</Chip>
            )}
          </p>
        </>
      )}
    </div>
  );
}
