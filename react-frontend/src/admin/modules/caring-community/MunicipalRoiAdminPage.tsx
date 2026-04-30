// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Spinner,
  Tooltip,
} from '@heroui/react';
import Building2 from 'lucide-react/icons/building-2';
import Clock from 'lucide-react/icons/clock';
import Heart from 'lucide-react/icons/heart';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import TrendingDown from 'lucide-react/icons/trending-down';
import TrendingUp from 'lucide-react/icons/trending-up';
import Users from 'lucide-react/icons/users';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader, StatCard } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

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
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const CHF = new Intl.NumberFormat('de-CH', {
  style: 'currency',
  currency: 'CHF',
  maximumFractionDigits: 0,
});

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function MunicipalRoiAdminPage() {
  usePageTitle('Municipal Impact Report');
  const { showToast } = useToast();

  const [data, setData] = useState<MunicipalRoi | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<MunicipalRoi>('/v2/admin/caring-community/municipal-roi');
      setData(res.data ?? null);
    } catch {
      showToast('Failed to load municipal impact data', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  const yoyPct = data?.trend.hours_yoy_pct ?? null;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Municipal Impact Report"
        subtitle="Evidence for B2G procurement — care cost offsets and social capital metrics"
        icon={<TrendingUp size={20} />}
        actions={
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
        }
      />

      {/* Methodology note */}
      <Card className="border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
        <CardBody className="flex flex-row items-start gap-3 py-3">
          <Info size={16} className="mt-0.5 shrink-0 text-default-500" />
          <p className="text-sm text-default-600">
            Hours are valued at CHF 35/hr (Swiss formal care assistant rate, SECO 2024). Prevention
            value applies a 2× multiplier per Age-Stiftung/KISS evaluation methodology.
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
            <StatCard
              label="Total Hours"
              value={data.total_hours.toLocaleString()}
              icon={Clock}
              color="primary"
            />
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
                    {yoyPct > 0 ? '+' : ''}{yoyPct.toFixed(1)}% YoY
                  </Chip>
                )}
              </div>
              <p className="text-sm text-default-500">in formal care costs prevented this period</p>

              <Divider />

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                  <p className="text-xs text-default-500 mb-1">Prevention value (2× multiplier)</p>
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
                  'Data can be exported for Age-Stiftung, Pro Senectute, and cantonal social department reporting',
                ].map((point) => (
                  <li key={point} className="flex items-start gap-2 text-sm text-default-700">
                    <span className="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" />
                    {point}
                  </li>
                ))}
              </ul>
            </CardBody>
          </Card>
        </>
      )}
    </div>
  );
}
