// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Accordion,
  AccordionItem,
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Select,
  SelectItem,
  Spinner,
  Tooltip,
} from '@heroui/react';
import Building from 'lucide-react/icons/building';
import Download from 'lucide-react/icons/download';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import Scale from 'lucide-react/icons/scale';
import ShieldCheck from 'lucide-react/icons/shield-check';
import Wrench from 'lucide-react/icons/wrench';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { Abbr, PageHeader } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type Classification = 'agpl_public' | 'tenant_config' | 'private_deployment' | 'commercial';

interface CategoryDef {
  key: string;
  label: string;
}

interface ClassificationDef {
  key: Classification;
  label: string;
  description: string;
}

interface Capability {
  key: string;
  label: string;
  description: string;
  category: string;
  default_classification: Classification;
  effective_classification: Classification;
  is_overridden: boolean;
  agpl_module: boolean;
  notes: string;
}

interface BoundaryMatrix {
  categories: CategoryDef[];
  classifications: ClassificationDef[];
  capabilities: Capability[];
  overrides_count: number;
  last_updated_at: string | null;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const CLASSIFICATION_COLOR: Record<
  Classification,
  'success' | 'primary' | 'warning' | 'secondary'
> = {
  agpl_public: 'success',
  tenant_config: 'primary',
  private_deployment: 'warning',
  commercial: 'secondary',
};

const CLASSIFICATION_OPTIONS: Classification[] = [
  'agpl_public',
  'tenant_config',
  'private_deployment',
  'commercial',
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

type AdminT = (key: string, options?: Record<string, unknown>) => string;

function buildMarkdown(matrix: BoundaryMatrix, t: AdminT): string {
  const lines: string[] = [];
  lines.push(`# ${t('commercial_boundary.meta.title')}`);
  lines.push('');
  lines.push(`_${t('commercial_boundary.meta.subtitle')}_`);
  lines.push('');
  if (matrix.last_updated_at) {
    lines.push(`_${t('commercial_boundary.export.last_updated', { date: matrix.last_updated_at })}_`);
    lines.push('');
  }

  lines.push(`## ${t('commercial_boundary.sections.classifications')}`);
  lines.push('');
  for (const c of matrix.classifications) {
    lines.push(`- **${c.label}** (\`${c.key}\`) — ${c.description}`);
  }
  lines.push('');

  for (const cat of matrix.categories) {
    const inCat = matrix.capabilities.filter((c) => c.category === cat.key);
    if (inCat.length === 0) continue;
    lines.push(`## ${cat.label}`);
    lines.push('');
    lines.push(`| ${t('commercial_boundary.export.capability')} | ${t('commercial_boundary.export.classification')} | ${t('commercial_boundary.export.agpl_module')} | ${t('commercial_boundary.export.default')} | ${t('commercial_boundary.export.overridden')} | ${t('commercial_boundary.export.notes')} |`);
    lines.push('|---|---|---|---|---|---|');
    for (const cap of inCat) {
      const effective = t(`commercial_boundary.classification.${cap.effective_classification}`);
      const def = t(`commercial_boundary.classification.${cap.default_classification}`);
      const cleanNotes = (cap.notes || '').replace(/\|/g, '\\|').replace(/\n/g, ' ');
      lines.push(
        `| **${cap.label}** — ${cap.description.replace(/\|/g, '\\|')} | ${effective} | ${cap.agpl_module ? t('commercial_boundary.export.yes') : t('commercial_boundary.export.no')} | ${def} | ${cap.is_overridden ? t('commercial_boundary.export.yes') : t('commercial_boundary.empty.value')} | ${cleanNotes || t('commercial_boundary.empty.value')} |`,
      );
    }
    lines.push('');
  }

  lines.push(`_${t('commercial_boundary.export.generated', { date: new Date().toISOString() })}_`);
  return lines.join('\n');
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

export default function CommercialBoundaryAdminPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('commercial_boundary.meta.page_title'));
  const { showToast } = useToast();

  const [matrix, setMatrix] = useState<BoundaryMatrix | null>(null);
  const [loading, setLoading] = useState(true);
  const [savingKey, setSavingKey] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<BoundaryMatrix>('/v2/admin/caring-community/commercial-boundary');
      setMatrix(res.data ?? null);
    } catch {
      showToast(t('commercial_boundary.toasts.load_failed'), 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast, t]);

  useEffect(() => {
    load();
  }, [load]);

  const setClassification = useCallback(
    async (capabilityKey: string, classification: Classification | null) => {
      setSavingKey(capabilityKey);
      try {
        const res = await api.put<BoundaryMatrix>(
          '/v2/admin/caring-community/commercial-boundary/override',
          { capability_key: capabilityKey, classification },
        );
        setMatrix(res.data ?? null);
        showToast(
          classification === null
            ? t('commercial_boundary.toasts.reverted')
            : t('commercial_boundary.toasts.updated'),
          'success',
        );
      } catch {
        showToast(t('commercial_boundary.toasts.update_failed'), 'error');
      } finally {
        setSavingKey(null);
      }
    },
    [showToast, t],
  );

  const exportMarkdown = useCallback(async () => {
    try {
      const res = await api.get<BoundaryMatrix>('/v2/admin/caring-community/commercial-boundary');
      if (!res.data) {
        showToast(t('commercial_boundary.toasts.export_empty'), 'error');
        return;
      }
      const md = buildMarkdown(res.data, t);
      const blob = new Blob([md], { type: 'text/markdown' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'commercial-boundary.md';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      showToast(t('commercial_boundary.toasts.exported'), 'success');
    } catch {
      showToast(t('commercial_boundary.toasts.export_failed'), 'error');
    }
  }, [showToast, t]);

  const groupedCapabilities = useMemo(() => {
    if (!matrix) return [];
    return matrix.categories
      .map((cat) => ({
        category: cat,
        items: matrix.capabilities.filter((c) => c.category === cat.key),
      }))
      .filter((g) => g.items.length > 0);
  }, [matrix]);

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('commercial_boundary.meta.title')}
        subtitle={t('commercial_boundary.meta.subtitle')}
        icon={<Scale size={20} />}
        actions={
          <div className="flex items-center gap-2">
            {matrix && matrix.overrides_count > 0 && (
              <Chip color="warning" variant="flat" size="sm">
                {t('commercial_boundary.overrides_count', { count: matrix.overrides_count })}
              </Chip>
            )}
            <Tooltip content={t('commercial_boundary.actions.export_markdown')}>
              <Button
                size="sm"
                variant="flat"
                startContent={<Download size={14} />}
                onPress={exportMarkdown}
                isDisabled={loading}
              >
                {t('commercial_boundary.actions.export')}
              </Button>
            </Tooltip>
            <Tooltip content={t('commercial_boundary.actions.refresh_data')}>
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                onPress={load}
                isLoading={loading}
                aria-label={t('commercial_boundary.actions.refresh_aria')}
              >
                <RefreshCw size={15} />
              </Button>
            </Tooltip>
          </div>
        }
      />

      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">{t('commercial_boundary.about.title')}</p>
              <p className="text-default-600">
                {t('commercial_boundary.about.body_prefix')}{' '}
                <Abbr term="KISS" />/<Abbr term="AGORIS" /> {t('commercial_boundary.about.body_suffix')}
              </p>
              <p className="text-default-600">
                {t('commercial_boundary.about.override_prefix')}{' '}
                <Abbr term="AGPL" /> {t('commercial_boundary.about.override_suffix')}
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Loading */}
      {loading && !matrix && (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      )}

      {/* Classification key card */}
      {matrix && (
        <Card>
          <CardHeader className="pb-2">
            <div className="flex items-center gap-2">
              <ShieldCheck size={16} className="text-primary" />
              <span className="font-semibold text-sm">{t('commercial_boundary.sections.classifications')}</span>
            </div>
          </CardHeader>
          <CardBody className="pt-0">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {matrix.classifications.map((c) => (
                <div
                  key={c.key}
                  className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-3"
                >
                  <div className="flex items-center gap-2 mb-1">
                    <Chip color={CLASSIFICATION_COLOR[c.key]} variant="flat" size="sm">
                      {c.label}
                    </Chip>
                    <code className="text-xs text-default-500">{c.key}</code>
                  </div>
                  <p className="text-sm text-default-600">{c.description}</p>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      )}

      {/* Categories */}
      {matrix && groupedCapabilities.length > 0 && (
        <Accordion
          variant="splitted"
          selectionMode="multiple"
          defaultExpandedKeys={groupedCapabilities.map((g) => g.category.key)}
        >
          {groupedCapabilities.map((group) => (
            <AccordionItem
              key={group.category.key}
              aria-label={group.category.label}
              title={
                <div className="flex items-center gap-3">
                  <Building size={16} className="text-default-500" />
                  <span className="font-semibold">{group.category.label}</span>
                  <Chip variant="flat" size="sm" color="default">
                    {group.items.length}
                  </Chip>
                </div>
              }
            >
              <div className="space-y-3 pb-2">
                {group.items.map((cap) => (
                  <CapabilityRow
                    key={cap.key}
                    capability={cap}
                    saving={savingKey === cap.key}
                    onChange={(cls) => setClassification(cap.key, cls)}
                    onReset={() => setClassification(cap.key, null)}
                    t={t}
                  />
                ))}
              </div>
            </AccordionItem>
          ))}
        </Accordion>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Capability row
// ---------------------------------------------------------------------------

interface CapabilityRowProps {
  capability: Capability;
  saving: boolean;
  onChange: (classification: Classification) => void;
  onReset: () => void;
  t: AdminT;
}

function CapabilityRow({ capability, saving, onChange, onReset, t }: CapabilityRowProps) {
  const effective = capability.effective_classification;

  return (
    <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2 mb-1">
            <span className="font-semibold text-sm text-foreground">{capability.label}</span>
            <Chip color={CLASSIFICATION_COLOR[effective]} variant="flat" size="sm">
              {t(`commercial_boundary.classification.${effective}`)}
            </Chip>
            {capability.is_overridden && (
              <Chip color="warning" variant="flat" size="sm">
                {t('commercial_boundary.labels.overridden')}
              </Chip>
            )}
            {capability.agpl_module ? (
              <Chip
                color="success"
                variant="dot"
                size="sm"
                startContent={<ShieldCheck size={10} />}
              >
                <Abbr term="AGPL" /> {t('commercial_boundary.labels.module')}
              </Chip>
            ) : (
              <Chip color="secondary" variant="dot" size="sm" startContent={<Wrench size={10} />}>
                {t('commercial_boundary.labels.out_of_tree')}
              </Chip>
            )}
          </div>
          <p className="text-sm text-default-600">{capability.description}</p>
          {capability.notes && (
            <p className="mt-1 text-xs text-default-500 italic">{capability.notes}</p>
          )}
          <p className="mt-2 text-xs text-default-500">
            {t('commercial_boundary.labels.canonical_default')}{' '}
            <span className="font-medium">
              {t(`commercial_boundary.classification.${capability.default_classification}`)}
            </span>
          </p>
        </div>

        <div className="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center">
          <Select
            size="sm"
            label={t('commercial_boundary.fields.set_classification')}
            selectedKeys={[effective]}
            onChange={(e) => {
              const next = e.target.value as Classification;
              if (next && next !== effective) {
                onChange(next);
              }
            }}
            isDisabled={saving}
            className="min-w-[200px]"
            aria-label={t('commercial_boundary.fields.classification_aria', { label: capability.label })}
          >
            {CLASSIFICATION_OPTIONS.map((opt) => (
              <SelectItem key={opt}>
                {t(`commercial_boundary.classification.${opt}`)}
              </SelectItem>
            ))}
          </Select>

          {capability.is_overridden && (
            <Tooltip content={t('commercial_boundary.actions.reset_default')}>
              <Button
                size="sm"
                variant="flat"
                color="warning"
                isIconOnly
                onPress={onReset}
                isLoading={saving}
                aria-label={t('commercial_boundary.actions.reset_default_aria')}
              >
                <RotateCcw size={14} />
              </Button>
            </Tooltip>
          )}
        </div>
      </div>

      <Divider className="mt-3" />
      <p className="mt-2 text-[10px] uppercase tracking-wide text-default-400">
        {t('commercial_boundary.labels.key')}: <code>{capability.key}</code>
      </p>
    </div>
  );
}
