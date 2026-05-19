// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Divider,
  Input,
  Select,
  SelectItem,
  Spinner,
  Tooltip,
} from '@heroui/react';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Save from 'lucide-react/icons/save';
import ScrollText from 'lucide-react/icons/scroll-text';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { useAdminPageMeta } from '../../AdminMetaContext';
import { Abbr, PageHeader } from '../../components';

interface FieldSchema {
  type: 'int' | 'int_nullable' | 'float' | 'enum' | 'url_nullable';
  default: string | number | null;
  choices?: string[];
  min?: number;
  max?: number;
}

interface OperatingPolicy {
  approval_authority: string;
  trusted_reviewer_threshold: number;
  sla_first_response_hours: number;
  sla_help_request_hours: number;
  legacy_hour_settlement: string;
  reciprocal_balance_threshold_hours: number;
  safeguarding_escalation_user_id: number | null;
  chf_hourly_rate: number;
  chf_prevention_multiplier: number;
  statement_cadence: string;
  policy_appendix_url: string | null;
}

interface PolicyResponse {
  policy: OperatingPolicy;
  schema: Record<string, FieldSchema>;
  last_updated_at: string | null;
}

export default function OperatingPolicyAdminPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('admin.operating_policy.meta.title'));
  useAdminPageMeta({
    title: t('admin.operating_policy.meta.title'),
    description: t('admin.operating_policy.meta.description'),
  });
  const { showToast } = useToast();

  const [data, setData] = useState<PolicyResponse | null>(null);
  const [draft, setDraft] = useState<OperatingPolicy | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<PolicyResponse>('/v2/admin/caring-community/operating-policy');
      setData(res.data ?? null);
      setDraft(res.data?.policy ?? null);
    } catch {
      showToast(t('admin.operating_policy.errors.load_failed'), 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const dirty = useMemo(() => {
    if (!data || !draft) return false;
    return JSON.stringify(data.policy) !== JSON.stringify(draft);
  }, [data, draft]);

  const setField = <K extends keyof OperatingPolicy>(key: K, value: OperatingPolicy[K]) => {
    if (!draft) return;
    setDraft({ ...draft, [key]: value });
  };

  const save = async () => {
    if (!draft) return;
    setSaving(true);
    try {
      const res = await api.put<OperatingPolicy>('/v2/admin/caring-community/operating-policy', draft);
      setData((prev) => (prev ? { ...prev, policy: res.data ?? prev.policy } : prev));
      setDraft(res.data ?? draft);
      showToast(t('admin.operating_policy.messages.saved'), 'success');
    } catch {
      showToast(t('admin.operating_policy.errors.save_failed'), 'error');
    } finally {
      setSaving(false);
    }
  };

  const renderField = (key: keyof OperatingPolicy) => {
    if (!draft || !data) return null;
    const schema = data.schema[key];
    if (!schema) return null;
    const label = t(`admin.operating_policy.fields.${key}.label`);
    const help = t(`admin.operating_policy.fields.${key}.help`);
    const value = draft[key];

    if (schema.type === 'enum') {
      return (
        <Select
          label={label}
          description={help}
          selectedKeys={value !== null && value !== undefined ? [String(value)] : []}
          onSelectionChange={(keys) => {
            const next = Array.from(keys)[0];
            if (typeof next === 'string') {
              setField(key, next as OperatingPolicy[typeof key]);
            }
          }}
        >
          {(schema.choices ?? []).map((opt) => (
            <SelectItem key={opt}>
              {t(`admin.operating_policy.choices.${key}.${opt}`, opt)}
            </SelectItem>
          ))}
        </Select>
      );
    }

    if (schema.type === 'url_nullable') {
      return (
        <Input
          label={label}
          description={help}
          value={(value as string | null) ?? ''}
          onValueChange={(v) => setField(key, (v || null) as OperatingPolicy[typeof key])}
          placeholder={t('admin.operating_policy.placeholders.url')}
        />
      );
    }

    return (
      <Input
        label={label}
        description={help}
        type="number"
        step={schema.type === 'float' ? '0.1' : '1'}
        min={schema.min}
        max={schema.max}
        value={value === null || value === undefined ? '' : String(value)}
        onValueChange={(v) => {
          if (v === '') {
            if (schema.type === 'int_nullable') {
              setField(key, null as OperatingPolicy[typeof key]);
            }
            return;
          }
          const n = schema.type === 'float' ? parseFloat(v) : parseInt(v, 10);
          if (!isNaN(n)) {
            setField(key, n as OperatingPolicy[typeof key]);
          }
        }}
      />
    );
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('admin.operating_policy.title')}
        subtitle={t('admin.operating_policy.subtitle')}
        icon={<ScrollText size={20} />}
        actions={
          <div className="flex gap-2">
            <Tooltip content={t('admin.operating_policy.actions.refresh')}>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                onPress={load}
                isLoading={loading}
                aria-label={t('admin.operating_policy.actions.refresh')}
              >
                <RefreshCw size={15} />
              </Button>
            </Tooltip>
            <Button
              size="sm"
              color="primary"
              startContent={<Save size={14} />}
              onPress={save}
              isLoading={saving}
              isDisabled={!dirty || saving}
            >
              {t('admin.operating_policy.actions.save')}
            </Button>
          </div>
        }
      />

      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">{t('admin.operating_policy.about.title')}</p>
              <p className="text-default-600">{t('admin.operating_policy.about.body')}</p>
              <p className="text-default-600">
                {t('admin.operating_policy.about.methodology_prefix')}
                {' '}<Abbr term="CHF" />{' '}
                {t('admin.operating_policy.about.methodology_middle')}
                {' '}<Abbr term="KISS">KISS</Abbr>{' '}
                {t('admin.operating_policy.about.methodology_suffix')}
                {' '}<Abbr term="AGORIS">AGORIS</Abbr>{' '}
                {t('admin.operating_policy.about.methodology_tail')}
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {loading && (
        <div className="flex justify-center py-16">
          <Spinner size="lg" label={t('admin.operating_policy.loading')} />
        </div>
      )}

      {!loading && draft && data && (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <Card>
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">{t('admin.operating_policy.sections.approvals')}</span>
            </CardHeader>
            <CardBody className="space-y-4 pt-0">
              {renderField('approval_authority')}
              {renderField('trusted_reviewer_threshold')}
            </CardBody>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">{t('admin.operating_policy.sections.sla')}</span>
            </CardHeader>
            <CardBody className="space-y-4 pt-0">
              {renderField('sla_first_response_hours')}
              {renderField('sla_help_request_hours')}
            </CardBody>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">{t('admin.operating_policy.sections.hours')}</span>
            </CardHeader>
            <CardBody className="space-y-4 pt-0">
              {renderField('legacy_hour_settlement')}
              {renderField('reciprocal_balance_threshold_hours')}
              {renderField('statement_cadence')}
            </CardBody>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">{t('admin.operating_policy.sections.safeguarding')}</span>
            </CardHeader>
            <CardBody className="space-y-4 pt-0">
              {renderField('safeguarding_escalation_user_id')}
            </CardBody>
          </Card>

          <Card className="lg:col-span-2">
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">
                <Abbr term="CHF" /> {t('admin.operating_policy.sections.social_value')}
              </span>
            </CardHeader>
            <CardBody className="grid grid-cols-1 gap-4 pt-0 sm:grid-cols-2">
              {renderField('chf_hourly_rate')}
              {renderField('chf_prevention_multiplier')}
            </CardBody>
          </Card>

          <Card className="lg:col-span-2">
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">{t('admin.operating_policy.sections.appendix')}</span>
            </CardHeader>
            <CardBody className="space-y-4 pt-0">
              {renderField('policy_appendix_url')}
            </CardBody>
          </Card>
        </div>
      )}

      {!loading && data?.last_updated_at && (
        <>
          <Divider />
          <p className="text-xs text-default-500">
            {t('admin.operating_policy.last_updated', {
              date: new Date(data.last_updated_at).toLocaleString(),
            })}
          </p>
        </>
      )}
    </div>
  );
}
