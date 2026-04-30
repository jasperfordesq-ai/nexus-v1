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
import { PageHeader } from '../../components';

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

const FIELD_LABELS: Record<keyof OperatingPolicy, string> = {
  approval_authority:                 'Approval authority',
  trusted_reviewer_threshold:         'Trusted-reviewer threshold (hours logged)',
  sla_first_response_hours:           'SLA — first response (hours)',
  sla_help_request_hours:             'SLA — help-request resolution (hours)',
  legacy_hour_settlement:             'Legacy-hour settlement policy',
  reciprocal_balance_threshold_hours: 'Reciprocal-balance intervention threshold (hours)',
  safeguarding_escalation_user_id:    'Safeguarding escalation owner (user ID)',
  chf_hourly_rate:                    'CHF social value per hour',
  chf_prevention_multiplier:          'CHF prevention multiplier',
  statement_cadence:                  'Member statement cadence',
  policy_appendix_url:                'Signed policy appendix URL',
};

const FIELD_HELP: Partial<Record<keyof OperatingPolicy, string>> = {
  approval_authority:                 'Who may finalise approved hours: tenant admin, coordinators, or both parties (mutual).',
  trusted_reviewer_threshold:         'Minimum approved hours before a member becomes a trusted reviewer / Trusted tier.',
  sla_first_response_hours:           'Target time to first response on a new help request.',
  sla_help_request_hours:             'Target time to close or match a help request.',
  legacy_hour_settlement:             'How a deceased member\'s remaining hours are settled.',
  reciprocal_balance_threshold_hours: 'When a member\'s give/receive imbalance triggers a coordinator check-in.',
  safeguarding_escalation_user_id:    'User ID receiving safeguarding escalations. Leave blank to use admins as a group.',
  chf_hourly_rate:                    'CHF value per care hour for ROI reporting (Swiss formal-care assistant rate).',
  chf_prevention_multiplier:          'Multiplier applied to formal-care offset (Age-Stiftung / KISS methodology).',
  statement_cadence:                  'How often members receive a per-member hour statement.',
  policy_appendix_url:                'Public URL of the signed policy appendix for the pilot.',
};

export default function OperatingPolicyAdminPage() {
  usePageTitle('Operating Policy');
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
      showToast('Failed to load operating policy', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    load();
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
      showToast('Operating policy saved', 'success');
    } catch (err) {
      const msg = (err as { message?: string })?.message ?? 'Failed to save operating policy';
      showToast(msg, 'error');
    } finally {
      setSaving(false);
    }
  };

  const renderField = (key: keyof OperatingPolicy) => {
    if (!draft || !data) return null;
    const schema = data.schema[key];
    if (!schema) return null;
    const label = FIELD_LABELS[key];
    const help = FIELD_HELP[key];
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
            <SelectItem key={opt}>{opt}</SelectItem>
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
          placeholder="https://..."
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
        title="Operating Policy"
        subtitle="AG81 — KISS operating-policy workshop: rules software cannot guess"
        icon={<ScrollText size={20} />}
        actions={
          <div className="flex gap-2">
            <Tooltip content="Refresh">
              <Button isIconOnly size="sm" variant="flat" onPress={load} isLoading={loading} aria-label="Refresh">
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
              Save changes
            </Button>
          </div>
        }
      />

      <Card className="border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
        <CardBody className="flex flex-row items-start gap-3 py-3">
          <Info size={16} className="mt-0.5 shrink-0 text-default-500" />
          <p className="text-sm text-default-600">
            These settings encode the human rules each pilot must agree before launch. They drive trust-tier promotion,
            SLA monitoring, hour-estate handling, balance interventions, ROI computation, and safeguarding escalation.
          </p>
        </CardBody>
      </Card>

      {loading && (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      )}

      {!loading && draft && data && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <Card>
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">Approvals & trust</span>
            </CardHeader>
            <CardBody className="pt-0 space-y-4">
              {renderField('approval_authority')}
              {renderField('trusted_reviewer_threshold')}
            </CardBody>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">SLA windows</span>
            </CardHeader>
            <CardBody className="pt-0 space-y-4">
              {renderField('sla_first_response_hours')}
              {renderField('sla_help_request_hours')}
            </CardBody>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">Hour management</span>
            </CardHeader>
            <CardBody className="pt-0 space-y-4">
              {renderField('legacy_hour_settlement')}
              {renderField('reciprocal_balance_threshold_hours')}
              {renderField('statement_cadence')}
            </CardBody>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">Safeguarding</span>
            </CardHeader>
            <CardBody className="pt-0 space-y-4">
              {renderField('safeguarding_escalation_user_id')}
            </CardBody>
          </Card>

          <Card className="lg:col-span-2">
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">CHF social-value methodology</span>
            </CardHeader>
            <CardBody className="pt-0 grid grid-cols-1 sm:grid-cols-2 gap-4">
              {renderField('chf_hourly_rate')}
              {renderField('chf_prevention_multiplier')}
            </CardBody>
          </Card>

          <Card className="lg:col-span-2">
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">Pilot policy appendix</span>
            </CardHeader>
            <CardBody className="pt-0 space-y-4">
              {renderField('policy_appendix_url')}
            </CardBody>
          </Card>
        </div>
      )}

      {!loading && data?.last_updated_at && (
        <>
          <Divider />
          <p className="text-xs text-default-500">
            Last updated {new Date(data.last_updated_at).toLocaleString()}
          </p>
        </>
      )}
    </div>
  );
}
