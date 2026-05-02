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
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

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

const CLASSIFICATION_LABEL: Record<Classification, string> = {
  agpl_public: 'AGPL public',
  tenant_config: 'Tenant config',
  private_deployment: 'Private deployment',
  commercial: 'Commercial',
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

function buildMarkdown(matrix: BoundaryMatrix): string {
  const lines: string[] = [];
  lines.push('# Commercial Boundary Map');
  lines.push('');
  lines.push(
    '_AG82 — what is AGPL public, tenant-configurable, deployment-layer, or commercial._',
  );
  lines.push('');
  if (matrix.last_updated_at) {
    lines.push(`_Last updated: ${matrix.last_updated_at}_`);
    lines.push('');
  }

  lines.push('## Classifications');
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
    lines.push('| Capability | Classification | AGPL module | Default | Overridden | Notes |');
    lines.push('|---|---|---|---|---|---|');
    for (const cap of inCat) {
      const effective = CLASSIFICATION_LABEL[cap.effective_classification] ?? cap.effective_classification;
      const def = CLASSIFICATION_LABEL[cap.default_classification] ?? cap.default_classification;
      const cleanNotes = (cap.notes || '').replace(/\|/g, '\\|').replace(/\n/g, ' ');
      lines.push(
        `| **${cap.label}** — ${cap.description.replace(/\|/g, '\\|')} | ${effective} | ${cap.agpl_module ? 'yes' : 'no'} | ${def} | ${cap.is_overridden ? 'yes' : '—'} | ${cleanNotes || '—'} |`,
      );
    }
    lines.push('');
  }

  lines.push(`_Generated ${new Date().toISOString()} from the NEXUS admin panel._`);
  return lines.join('\n');
}

// ---------------------------------------------------------------------------
// Main page
// ---------------------------------------------------------------------------

export default function CommercialBoundaryAdminPage() {
  usePageTitle('Commercial Boundary Map');
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
      showToast('Failed to load commercial boundary map', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

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
            ? 'Reverted to canonical default'
            : 'Classification updated',
          'success',
        );
      } catch {
        showToast('Failed to update classification', 'error');
      } finally {
        setSavingKey(null);
      }
    },
    [showToast],
  );

  const exportMarkdown = useCallback(async () => {
    try {
      const res = await api.get<BoundaryMatrix>('/v2/admin/caring-community/commercial-boundary');
      if (!res.data) {
        showToast('Export returned empty', 'error');
        return;
      }
      const md = buildMarkdown(res.data);
      const blob = new Blob([md], { type: 'text/markdown' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'commercial-boundary.md';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      showToast('Boundary map exported', 'success');
    } catch {
      showToast('Failed to export boundary map', 'error');
    }
  }, [showToast]);

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
        title="Commercial Boundary Map"
        subtitle="AG82 — what is AGPL public, tenant-configurable, deployment-layer, or commercial"
        icon={<Scale size={20} />}
        actions={
          <div className="flex items-center gap-2">
            {matrix && matrix.overrides_count > 0 && (
              <Chip color="warning" variant="flat" size="sm">
                {matrix.overrides_count} override{matrix.overrides_count === 1 ? '' : 's'}
              </Chip>
            )}
            <Tooltip content="Export as Markdown">
              <Button
                size="sm"
                variant="flat"
                startContent={<Download size={14} />}
                onPress={exportMarkdown}
                isDisabled={loading}
              >
                Export
              </Button>
            </Tooltip>
            <Tooltip content="Refresh data">
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                onPress={load}
                isLoading={loading}
                aria-label="Refresh"
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
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                The Commercial Boundary defines which types of paid work are permitted within the
                community care programme and which must remain unpaid time credits. This boundary is
                a requirement of the KISS/AGORIS methodology and must be agreed with your municipal
                partner before the pilot launches. The matrix below lists activity categories and
                their permitted exchange type.
              </p>
              <p className="text-default-600">
                Use the per-row override to record a tenant-specific classification (for example, a
                private deployment that has a commercial bundle). Overrides are stored in tenant
                settings only — they never alter the canonical public classification shipped with the
                AGPL repo. Once you confirm the boundary matrix, the agreement is recorded with a
                timestamp and your user ID, forming part of the pilot governance documentation that
                appears in the Pilot Launch Readiness check.
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
              <span className="font-semibold text-sm">Classifications</span>
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
}

function CapabilityRow({ capability, saving, onChange, onReset }: CapabilityRowProps) {
  const effective = capability.effective_classification;

  return (
    <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2 mb-1">
            <span className="font-semibold text-sm text-foreground">{capability.label}</span>
            <Chip color={CLASSIFICATION_COLOR[effective]} variant="flat" size="sm">
              {CLASSIFICATION_LABEL[effective]}
            </Chip>
            {capability.is_overridden && (
              <Chip color="warning" variant="flat" size="sm">
                Overridden
              </Chip>
            )}
            {capability.agpl_module ? (
              <Chip
                color="success"
                variant="dot"
                size="sm"
                startContent={<ShieldCheck size={10} />}
              >
                AGPL module
              </Chip>
            ) : (
              <Chip color="secondary" variant="dot" size="sm" startContent={<Wrench size={10} />}>
                Out-of-tree
              </Chip>
            )}
          </div>
          <p className="text-sm text-default-600">{capability.description}</p>
          {capability.notes && (
            <p className="mt-1 text-xs text-default-500 italic">{capability.notes}</p>
          )}
          <p className="mt-2 text-xs text-default-500">
            Canonical default:{' '}
            <span className="font-medium">
              {CLASSIFICATION_LABEL[capability.default_classification]}
            </span>
          </p>
        </div>

        <div className="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center">
          <Select
            size="sm"
            label="Set classification"
            selectedKeys={[effective]}
            onChange={(e) => {
              const next = e.target.value as Classification;
              if (next && next !== effective) {
                onChange(next);
              }
            }}
            isDisabled={saving}
            className="min-w-[200px]"
            aria-label={`Classification for ${capability.label}`}
          >
            {CLASSIFICATION_OPTIONS.map((opt) => (
              <SelectItem key={opt}>
                {CLASSIFICATION_LABEL[opt]}
              </SelectItem>
            ))}
          </Select>

          {capability.is_overridden && (
            <Tooltip content="Reset to canonical default">
              <Button
                size="sm"
                variant="flat"
                color="warning"
                isIconOnly
                onPress={onReset}
                isLoading={saving}
                aria-label="Reset to default"
              >
                <RotateCcw size={14} />
              </Button>
            </Tooltip>
          )}
        </div>
      </div>

      <Divider className="mt-3" />
      <p className="mt-2 text-[10px] uppercase tracking-wide text-default-400">
        Key: <code>{capability.key}</code>
      </p>
    </div>
  );
}
