// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Card, CardBody, Chip, Select, SelectItem, Spinner, Table, TableBody, TableCell, TableColumn, TableHeader, TableRow } from '@heroui/react';
import BarChart3 from 'lucide-react/icons/bar-chart-3';
import Clock from 'lucide-react/icons/clock';
import DollarSign from 'lucide-react/icons/dollar-sign';
import MessageSquare from 'lucide-react/icons/message-square';
import ThumbsUp from 'lucide-react/icons/thumbs-up';
import ThumbsDown from 'lucide-react/icons/thumbs-down';
import type { LucideIcon } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';

interface ToolUsage {
  name: string;
  calls: number;
}

interface Unanswered {
  id: number;
  user_text: string;
  assistant_text: string;
  note: string | null;
  at: string | null;
  model: string | null;
}

interface Metrics {
  window_days: number;
  turns: number;
  tokens_total: number;
  cost_usd: number;
  avg_latency_ms: number;
  thumbs_up: number;
  thumbs_down: number;
  top_tools: ToolUsage[];
  unanswered: Unanswered[];
}

const WINDOWS = ['7', '30', '90'] as const;

export default function AiTraceMetricsAdminPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('ai.trace_metrics.meta.title'));
  const toast = useToast();
  const [days, setDays] = useState('30');
  const [metrics, setMetrics] = useState<Metrics | null>(null);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async (window: string) => {
    setLoading(true);
    try {
      const res = await api.get<Metrics>(`/v2/admin/ai-traces/metrics?days=${window}`);
      setMetrics(res.data ?? null);
    } catch {
      toast.error(t('ai.trace_metrics.toasts.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [t, toast]);

  useEffect(() => {
    void load(days);
  }, [days, load]);

  return (
    <div className="space-y-6 p-6">
      <div className="flex items-center gap-3">
        <BarChart3 size={28} className="text-primary" />
        <div>
          <h1 className="text-2xl font-bold">{t('ai.trace_metrics.meta.title')}</h1>
          <p className="text-sm text-default-500">
            {t('ai.trace_metrics.meta.description')}
          </p>
        </div>
        <div className="ml-auto">
          <Select
            size="sm"
            aria-label={t('ai.trace_metrics.filters.window')}
            selectedKeys={new Set([days])}
            onSelectionChange={(keys) => setDays(String(Array.from(keys)[0] ?? '30'))}
            className="w-44"
          >
            {WINDOWS.map((w) => (
              <SelectItem key={w}>{t(`ai.trace_metrics.windows.${w}`)}</SelectItem>
            ))}
          </Select>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <Stat icon={MessageSquare} label={t('ai.trace_metrics.stats.turns')} value={metrics?.turns.toLocaleString() ?? t('ai.common.empty_dash')} loading={loading} />
        <Stat icon={DollarSign} label={t('ai.trace_metrics.stats.cost')} value={metrics ? `$${metrics.cost_usd.toFixed(2)}` : t('ai.common.empty_dash')} loading={loading} />
        <Stat icon={Clock} label={t('ai.trace_metrics.stats.avg_latency')} value={metrics ? t('ai.trace_metrics.stats.latency_value', { value: metrics.avg_latency_ms }) : t('ai.common.empty_dash')} loading={loading} />
        <Stat icon={ThumbsUp} label={t('ai.trace_metrics.stats.thumbs_up')} value={metrics?.thumbs_up.toLocaleString() ?? t('ai.common.empty_dash')} loading={loading} color="success" />
        <Stat icon={ThumbsDown} label={t('ai.trace_metrics.stats.thumbs_down')} value={metrics?.thumbs_down.toLocaleString() ?? t('ai.common.empty_dash')} loading={loading} color="danger" />
      </div>

      <Card>
        <CardBody className="p-4 gap-3">
          <p className="font-semibold">{t('ai.trace_metrics.tools.title')}</p>
          {metrics && metrics.top_tools.length > 0 ? (
            <div className="flex flex-wrap gap-2">
              {metrics.top_tools.map((t) => (
                <Chip key={t.name} variant="flat" color="primary">
                  {t.name} - {t.calls}
                </Chip>
              ))}
            </div>
          ) : loading ? (
            <div className="flex items-center gap-2 text-sm text-default-500">
              <Spinner size="sm" />
              {t('ai.trace_metrics.empty.loading_tools')}
            </div>
          ) : (
            <p className="text-sm text-default-400">{t('ai.trace_metrics.empty.no_tools')}</p>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardBody className="p-4 gap-3">
          <div>
            <p className="font-semibold">{t('ai.trace_metrics.unanswered.title')}</p>
            <p className="text-xs text-default-500">
              {t('ai.trace_metrics.unanswered.description')}
            </p>
          </div>
          <Table aria-label={t('ai.trace_metrics.unanswered.table_aria')} isStriped removeWrapper>
            <TableHeader>
              <TableColumn>{t('ai.trace_metrics.unanswered.columns.when')}</TableColumn>
              <TableColumn>{t('ai.trace_metrics.unanswered.columns.user_question')}</TableColumn>
              <TableColumn>{t('ai.trace_metrics.unanswered.columns.assistant_reply')}</TableColumn>
              <TableColumn>{t('ai.trace_metrics.unanswered.columns.note')}</TableColumn>
            </TableHeader>
            <TableBody emptyContent={loading ? t('ai.common.loading') : t('ai.trace_metrics.empty.no_downvotes')}>
              {(metrics?.unanswered ?? []).map((u) => (
                <TableRow key={u.id}>
                  <TableCell>
                    <span className="text-xs">{u.at ? new Date(u.at).toLocaleString() : t('ai.common.empty_dash')}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm line-clamp-2 max-w-[20rem] block">{u.user_text}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-xs text-default-500 line-clamp-2 max-w-[24rem] block">{u.assistant_text}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-xs italic">{u.note ?? t('ai.common.empty_dash')}</span>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>
    </div>
  );
}

interface StatProps {
  icon: LucideIcon;
  label: string;
  value: string;
  loading?: boolean;
  color?: 'success' | 'danger' | 'default';
}

function Stat({ icon: Icon, label, value, loading, color = 'default' }: StatProps) {
  const tint = color === 'success' ? 'text-success' : color === 'danger' ? 'text-danger' : 'text-primary';
  return (
    <Card>
      <CardBody className="p-3 gap-1">
        <div className={`flex items-center gap-2 text-xs text-default-500`}>
          <Icon size={14} className={tint} />
          {label}
        </div>
        <p className="text-2xl font-bold">{loading ? '…' : value}</p>
      </CardBody>
    </Card>
  );
}
