// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AI Trace Metrics — admin dashboard.
 *
 * Shows aggregate cost, latency, tool usage, and a feed of "unanswered"
 * (downvoted) turns from the last N days. The unanswered list is the
 * feedback loop into AI Module Docs — admins should write a doc covering
 * any pattern they see here.
 *
 * ADMIN IS ENGLISH-ONLY — NO t() calls.
 */

import { useCallback, useEffect, useState } from 'react';
import { Card, CardBody, Chip, Select, SelectItem, Table, TableBody, TableCell, TableColumn, TableHeader, TableRow } from '@heroui/react';
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

const WINDOWS = [
  { value: '7', label: 'Last 7 days' },
  { value: '30', label: 'Last 30 days' },
  { value: '90', label: 'Last 90 days' },
] as const;

export default function AiTraceMetricsAdminPage() {
  usePageTitle('AI Trace Metrics');
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
      toast.error('Failed to load metrics');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    void load(days);
  }, [days, load]);

  return (
    <div className="space-y-6 p-6">
      <div className="flex items-center gap-3">
        <BarChart3 size={28} className="text-primary" />
        <div>
          <h1 className="text-2xl font-bold">AI Trace Metrics</h1>
          <p className="text-sm text-default-500">
            Cost, latency, tool usage, and unanswered questions for the AI chat assistant.
          </p>
        </div>
        <div className="ml-auto">
          <Select
            size="sm"
            aria-label="Window"
            selectedKeys={new Set([days])}
            onSelectionChange={(keys) => setDays(String(Array.from(keys)[0] ?? '30'))}
            className="w-44"
          >
            {WINDOWS.map((w) => (
              <SelectItem key={w.value}>{w.label}</SelectItem>
            ))}
          </Select>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <Stat icon={MessageSquare} label="Turns" value={metrics?.turns.toLocaleString() ?? '—'} loading={loading} />
        <Stat icon={DollarSign} label="Cost (USD)" value={metrics ? `$${metrics.cost_usd.toFixed(2)}` : '—'} loading={loading} />
        <Stat icon={Clock} label="Avg latency" value={metrics ? `${metrics.avg_latency_ms} ms` : '—'} loading={loading} />
        <Stat icon={ThumbsUp} label="Thumbs up" value={metrics?.thumbs_up.toLocaleString() ?? '—'} loading={loading} color="success" />
        <Stat icon={ThumbsDown} label="Thumbs down" value={metrics?.thumbs_down.toLocaleString() ?? '—'} loading={loading} color="danger" />
      </div>

      <Card>
        <CardBody className="p-4 gap-3">
          <p className="font-semibold">Top tools called</p>
          {metrics && metrics.top_tools.length > 0 ? (
            <div className="flex flex-wrap gap-2">
              {metrics.top_tools.map((t) => (
                <Chip key={t.name} variant="flat" color="primary">
                  {t.name} — {t.calls}
                </Chip>
              ))}
            </div>
          ) : (
            <p className="text-sm text-default-400">No tool calls in this window.</p>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardBody className="p-4 gap-3">
          <div>
            <p className="font-semibold">Unanswered questions (thumbs-down)</p>
            <p className="text-xs text-default-500">
              These are turns members gave a thumbs-down on. Write a new AI Module Doc to cover any recurring patterns.
            </p>
          </div>
          <Table aria-label="Unanswered" isStriped removeWrapper>
            <TableHeader>
              <TableColumn>When</TableColumn>
              <TableColumn>User question</TableColumn>
              <TableColumn>Assistant reply (excerpt)</TableColumn>
              <TableColumn>Note</TableColumn>
            </TableHeader>
            <TableBody emptyContent={loading ? 'Loading…' : 'No downvotes — nothing to improve right now.'}>
              {(metrics?.unanswered ?? []).map((u) => (
                <TableRow key={u.id}>
                  <TableCell>
                    <span className="text-xs">{u.at ? new Date(u.at).toLocaleString() : '—'}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm line-clamp-2 max-w-[20rem] block">{u.user_text}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-xs text-default-500 line-clamp-2 max-w-[24rem] block">{u.assistant_text}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-xs italic">{u.note ?? '—'}</span>
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
