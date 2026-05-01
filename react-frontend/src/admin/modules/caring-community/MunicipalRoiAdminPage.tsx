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
  Select,
  SelectItem,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Tooltip,
} from '@heroui/react';
import Building2 from 'lucide-react/icons/building-2';
import Clock from 'lucide-react/icons/clock';
import Download from 'lucide-react/icons/download';
import Heart from 'lucide-react/icons/heart';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import TrendingDown from 'lucide-react/icons/trending-down';
import TrendingUp from 'lucide-react/icons/trending-up';
import Users from 'lucide-react/icons/users';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api, tokenManager } from '@/lib/api';
import { PageHeader, StatCard } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface MunicipalRoiBreakdownRow {
  sub_region_id: number;
  sub_region_name: string;
  hours: number;
  weighted_hours: number;
  formal_care_offset_chf: number;
}

interface MunicipalRoiMethodology {
  hourly_rate_chf: number;
  hourly_rate_source: 'tenant_setting' | 'default' | string;
  prevention_multiplier: number;
  substitution_applied: boolean;
}

interface MunicipalRoi {
  total_hours: number;
  active_members: number;
  active_relationships: number;
  recipient_count: number;
  total_exchanges: number;
  roi: {
    hourly_rate_chf: number;
    formal_care_offset_chf: number;
    prevention_value_chf: number;
    social_isolation_prevented: number;
  };
  trend: {
    hours_yoy_pct: number | null;
  };
  // NEW (optional — backend may or may not have shipped yet)
  period?: { from: string; to: string };
  weighted_hours?: number;
  methodology?: MunicipalRoiMethodology;
  breakdown_by_sub_region?: MunicipalRoiBreakdownRow[];
}

interface SubRegionLite {
  id: number;
  name: string;
}

interface SubRegionListResponse {
  data?: SubRegionLite[];
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const CHF = new Intl.NumberFormat('de-CH', {
  style: 'currency',
  currency: 'CHF',
  maximumFractionDigits: 0,
});

const NUM = new Intl.NumberFormat('de-CH', { maximumFractionDigits: 0 });

function startOfYearISO(): string {
  const now = new Date();
  return `${now.getFullYear()}-01-01`;
}

function todayISO(): string {
  const now = new Date();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  return `${now.getFullYear()}-${m}-${d}`;
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function MunicipalRoiAdminPage() {
  usePageTitle('Municipal Impact Report');
  const { showToast } = useToast();

  const [data, setData] = useState<MunicipalRoi | null>(null);
  const [loading, setLoading] = useState(true);

  // Filters
  const [from, setFrom] = useState<string>(startOfYearISO());
  const [to, setTo] = useState<string>(todayISO());
  const [subRegionId, setSubRegionId] = useState<string>('');

  // Sub-regions
  const [subRegions, setSubRegions] = useState<SubRegionLite[]>([]);
  const [exporting, setExporting] = useState(false);

  // Build query string for filters
  const queryParams = useMemo(() => {
    const params = new URLSearchParams();
    if (from) params.append('from', from);
    if (to) params.append('to', to);
    if (subRegionId) params.append('sub_region_id', subRegionId);
    return params.toString();
  }, [from, to, subRegionId]);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const url =
        '/v2/admin/caring-community/municipal-roi' + (queryParams ? `?${queryParams}` : '');
      const res = await api.get<MunicipalRoi>(url);
      setData(res.data ?? null);
    } catch {
      showToast('Failed to load municipal impact data', 'error');
    } finally {
      setLoading(false);
    }
  }, [queryParams, showToast]);

  // Fetch sub-regions once on mount; silently hide on failure / empty
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const res = await api.get<SubRegionListResponse | SubRegionLite[]>(
          '/v2/admin/caring-community/sub-regions?status=active&per_page=200',
        );
        const payload = res.data;
        const list: SubRegionLite[] = Array.isArray(payload)
          ? payload
          : Array.isArray(payload?.data)
            ? payload.data
            : [];
        if (!cancelled) {
          setSubRegions(list.map((r) => ({ id: r.id, name: r.name })));
        }
      } catch {
        if (!cancelled) setSubRegions([]);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const yoyPct = data?.trend.hours_yoy_pct ?? null;

  const handleExport = useCallback(async () => {
    setExporting(true);
    try {
      const token = tokenManager.getAccessToken();
      const tenantId = tokenManager.getTenantId();
      const headers: Record<string, string> = {};
      if (token) headers['Authorization'] = `Bearer ${token}`;
      if (tenantId) headers['X-Tenant-ID'] = tenantId;

      const apiBase = import.meta.env.VITE_API_BASE || '/api';
      const url =
        `${apiBase}/v2/admin/caring-community/municipal-roi/export` +
        (queryParams ? `?${queryParams}` : '');

      const res = await fetch(url, { headers, credentials: 'include' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const blob = await res.blob();
      const objectUrl = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = objectUrl;
      a.download = `municipal-impact-${from}_to_${to}.csv`;
      a.click();
      URL.revokeObjectURL(objectUrl);
    } catch {
      showToast('Failed to export CSV', 'error');
    } finally {
      setExporting(false);
    }
  }, [queryParams, from, to, showToast]);

  // Sorted breakdown rows (by formal_care_offset_chf desc)
  const sortedBreakdown = useMemo(() => {
    if (!data?.breakdown_by_sub_region?.length) return [];
    return [...data.breakdown_by_sub_region].sort(
      (a, b) => b.formal_care_offset_chf - a.formal_care_offset_chf,
    );
  }, [data?.breakdown_by_sub_region]);

  // Whether weighted hours differ enough from total hours to show annotation
  const showWeightedAnnotation =
    !!data &&
    typeof data.weighted_hours === 'number' &&
    Math.abs(data.weighted_hours - data.total_hours) > 0.5;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Municipal Impact Report"
        subtitle="Evidence for B2G procurement — care cost offsets and social capital metrics"
        icon={<TrendingUp size={20} />}
        actions={
          <div className="flex items-center gap-2 flex-wrap">
            <Button
              size="sm"
              variant="flat"
              color="primary"
              startContent={<Download size={15} />}
              onPress={handleExport}
              isLoading={exporting}
            >
              Export CSV
            </Button>
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

      {/* Filters */}
      <Card className="border border-[var(--color-border)]">
        <CardBody className="flex flex-row flex-wrap items-end gap-3 py-3">
          <Input
            type="date"
            size="sm"
            label="From"
            labelPlacement="outside"
            value={from}
            onValueChange={setFrom}
            className="w-44"
            variant="bordered"
          />
          <Input
            type="date"
            size="sm"
            label="To"
            labelPlacement="outside"
            value={to}
            onValueChange={setTo}
            className="w-44"
            variant="bordered"
          />
          {subRegions.length > 0 && (
            <Select
              size="sm"
              label="Sub-region"
              labelPlacement="outside"
              placeholder="All sub-regions"
              selectedKeys={subRegionId ? [subRegionId] : []}
              onSelectionChange={(keys) => {
                const arr = Array.from(keys as Set<string | number>);
                setSubRegionId(arr.length ? String(arr[0]) : '');
              }}
              className="w-56"
              variant="bordered"
            >
              <>
                <SelectItem key="">All sub-regions</SelectItem>
                {subRegions.map((sr) => (
                  <SelectItem key={String(sr.id)}>{sr.name}</SelectItem>
                ))}
              </>
            </Select>
          )}
          <div className="ml-auto text-xs text-default-500">
            {data?.period
              ? `Period: ${data.period.from} → ${data.period.to}`
              : `Period: ${from} → ${to}`}
          </div>
        </CardBody>
      </Card>

      {/* Methodology note */}
      <Card className="border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
        <CardBody className="flex flex-row items-start gap-3 py-3">
          <Info size={16} className="mt-0.5 shrink-0 text-default-500" />
          <p className="text-sm text-default-600">
            {data?.methodology ? (
              <>
                Hours valued at CHF {NUM.format(data.methodology.hourly_rate_chf)}/hr (
                {data.methodology.hourly_rate_source === 'tenant_setting'
                  ? 'configured for this tenant'
                  : 'Swiss formal care assistant rate, SECO 2024'}
                ). Prevention value applies a {data.methodology.prevention_multiplier}× multiplier
                per Age-Stiftung/KISS evaluation methodology.{' '}
                {data.methodology.substitution_applied
                  ? 'Hours are weighted by per-category substitution coefficients.'
                  : 'All hours weighted at 1.0×.'}
              </>
            ) : (
              <>
                Hours are valued at CHF 35/hr (Swiss formal care assistant rate, SECO 2024).
                Prevention value applies a 2× multiplier per Age-Stiftung/KISS evaluation
                methodology.
              </>
            )}
          </p>
        </CardBody>
      </Card>

      {/* Loading */}
      {loading && (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      )}

      {data && !loading && (
        <>
          {/* Top KPI row */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <StatCard
                label="Total Hours"
                value={data.total_hours.toLocaleString()}
                icon={Clock}
                color="primary"
              />
              {showWeightedAnnotation && typeof data.weighted_hours === 'number' && (
                <Tooltip
                  content="Hours are weighted by category-specific substitution rates (e.g. companionship 0.4×, transport 0.7×, errands 1.0×) per Age-Stiftung methodology."
                >
                  <p className="mt-1 text-xs text-default-500 cursor-help">
                    {NUM.format(data.weighted_hours)} weighted hours after substitution coefficients
                  </p>
                </Tooltip>
              )}
            </div>
            <StatCard
              label="Active Members"
              value={data.active_members.toLocaleString()}
              icon={Users}
              color="success"
            />
            <StatCard
              label="Active Relationships"
              value={data.active_relationships.toLocaleString()}
              icon={Heart}
              color="secondary"
            />
            <StatCard
              label="Care Recipients"
              value={data.recipient_count.toLocaleString()}
              icon={Building2}
              color="warning"
            />
          </div>

          {/* ROI section */}
          <Card>
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">Estimated Care Cost Offset</span>
            </CardHeader>
            <CardBody className="pt-0 space-y-4">
              {/* Primary figure */}
              <div className="flex flex-wrap items-end gap-3">
                <span className="text-4xl font-extrabold text-foreground">
                  {CHF.format(data.roi.formal_care_offset_chf)}
                </span>
                {yoyPct !== null && (
                  <Chip
                    color={yoyPct >= 0 ? 'success' : 'danger'}
                    variant="flat"
                    size="sm"
                    startContent={
                      yoyPct >= 0 ? <TrendingUp size={12} /> : <TrendingDown size={12} />
                    }
                  >
                    {yoyPct > 0 ? '+' : ''}
                    {yoyPct.toFixed(1)}% YoY
                  </Chip>
                )}
              </div>
              <p className="text-sm text-default-500">in formal care costs prevented this period</p>

              <Divider />

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                  <p className="text-xs text-default-500 mb-1">
                    Prevention value
                    {data.methodology
                      ? ` (${data.methodology.prevention_multiplier}× multiplier)`
                      : ' (2× multiplier)'}
                  </p>
                  <p className="text-2xl font-bold">{CHF.format(data.roi.prevention_value_chf)}</p>
                </div>
                <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                  <p className="text-xs text-default-500 mb-1">
                    People supported out of social isolation
                  </p>
                  <p className="text-2xl font-bold">
                    {data.roi.social_isolation_prevented.toLocaleString()}
                  </p>
                </div>
              </div>
            </CardBody>
          </Card>

          {/* Municipalities context card */}
          <Card>
            <CardHeader className="pb-2">
              <span className="font-semibold text-sm">What this means for municipalities</span>
            </CardHeader>
            <CardBody className="pt-0">
              <ul className="space-y-2">
                {[
                  'Every hour of community caring time saves approximately CHF 35 in formal care costs',
                  'Preventative community support can reduce residential care needs — estimated 2× multiplier',
                  'NEXUS makes this impact measurable, auditable, and reportable for cantonal and municipal procurement',
                  'CSV export available — use the button in the page header for Age-Stiftung, Pro Senectute, and cantonal social department reporting.',
                ].map((point) => (
                  <li key={point} className="flex items-start gap-2 text-sm text-default-700">
                    <span className="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" />
                    {point}
                  </li>
                ))}
              </ul>
            </CardBody>
          </Card>

          {/* Sub-region breakdown */}
          {sortedBreakdown.length > 0 && (
            <Card>
              <CardHeader className="pb-2">
                <span className="font-semibold text-sm">Breakdown by sub-region</span>
              </CardHeader>
              <CardBody className="pt-0">
                <Table aria-label="Sub-region breakdown" removeWrapper>
                  <TableHeader>
                    <TableColumn>Sub-region</TableColumn>
                    <TableColumn>Hours</TableColumn>
                    <TableColumn>Weighted hours</TableColumn>
                    <TableColumn>Formal care offset</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {sortedBreakdown.map((row) => (
                      <TableRow key={row.sub_region_id}>
                        <TableCell>{row.sub_region_name}</TableCell>
                        <TableCell>{NUM.format(row.hours)}</TableCell>
                        <TableCell>{NUM.format(row.weighted_hours)}</TableCell>
                        <TableCell>{CHF.format(row.formal_care_offset_chf)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardBody>
            </Card>
          )}
        </>
      )}
    </div>
  );
}
