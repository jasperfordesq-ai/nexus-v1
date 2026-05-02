// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Tab,
  Tabs,
  Tooltip,
} from '@heroui/react';
import AlarmClock from 'lucide-react/icons/alarm-clock';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Settings from 'lucide-react/icons/settings';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { Abbr, PageHeader } from '../../components';

type Bucket = 'breached' | 'at_risk' | 'on_track';
type SlaDimension = 'first_response' | 'resolution';

interface OpenRequest {
  id: number;
  user_id: number;
  what: string;
  when_needed: string;
  status: string;
  created_at: string;
  updated_at: string | null;
  age_hours: number;
  sla_dimension: SlaDimension;
  sla_target_hours: number;
  sla_remaining_hours: number;
  sla_overage_hours: number;
  bucket: Bucket;
}

interface ResolvedRequest {
  id: number;
  user_id: number;
  what: string;
  status: string;
  created_at: string;
  updated_at: string | null;
  age_hours: number;
  turnaround_hours?: number;
  within_resolution_sla?: boolean;
}

interface SlaDashboard {
  policy: {
    first_response_hours: number;
    resolution_hours: number;
    source: 'platform_defaults' | 'tenant_policy';
  };
  summary: {
    pending: number;
    in_progress: number;
    first_response_breached: number;
    first_response_at_risk: number;
    resolution_breached: number;
    resolution_at_risk: number;
    resolved_within_window_24h: number;
  };
  open_requests: OpenRequest[];
  recently_resolved: ResolvedRequest[];
  generated_at: string;
}

const BUCKET_COLORS: Record<Bucket, 'danger' | 'warning' | 'success'> = {
  breached: 'danger',
  at_risk: 'warning',
  on_track: 'success',
};

const BUCKET_LABELS: Record<Bucket, string> = {
  breached: 'Breached',
  at_risk: 'At risk',
  on_track: 'On track',
};

const DIMENSION_LABELS: Record<SlaDimension, string> = {
  first_response: 'First response',
  resolution: 'Resolution',
};

function fmtHours(h: number): string {
  if (h < 1) return `${Math.round(h * 60)}m`;
  if (h < 24) return `${h.toFixed(1)}h`;
  const days = Math.floor(h / 24);
  const remHours = Math.round(h - days * 24);
  return `${days}d ${remHours}h`;
}

export default function HelpRequestSlaAdminPage() {
  usePageTitle('Help Request SLA Dashboard');
  const { showToast } = useToast();

  const [data, setData] = useState<SlaDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [bucketFilter, setBucketFilter] = useState<'all' | Bucket>('all');

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<SlaDashboard>('/v2/admin/caring-community/sla-dashboard');
      setData(res.data ?? null);
    } catch {
      showToast('Failed to load SLA dashboard', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  const filteredOpen = useMemo(() => {
    if (!data) return [];
    if (bucketFilter === 'all') return data.open_requests;
    return data.open_requests.filter((r) => r.bucket === bucketFilter);
  }, [data, bucketFilter]);

  const summary = data?.summary;
  const policy = data?.policy;

  const bucketCounts = useMemo(() => {
    if (!data) return { breached: 0, at_risk: 0, on_track: 0 };
    return data.open_requests.reduce<Record<Bucket, number>>(
      (acc, r) => {
        acc[r.bucket] = (acc[r.bucket] ?? 0) + 1;
        return acc;
      },
      { breached: 0, at_risk: 0, on_track: 0 },
    );
  }, [data]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Help Request SLA Dashboard"
        subtitle={<>AG96 — first-response and resolution <Abbr term="SLA">SLA</Abbr> tracking against AG81 operating policy</>}
        icon={<AlarmClock size={20} />}
        actions={
          <div className="flex items-center gap-2">
            <Button
              as={Link}
              to="/admin/caring-community/operating-policy"
              size="sm"
              variant="flat"
              startContent={<Settings size={14} />}
            >
              Edit policy
            </Button>
            <Tooltip content="Refresh">
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

      {/* About card */}
      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                Service Level Agreements (SLAs) set the maximum expected response and resolution times for help
                requests. When a request approaches or exceeds its SLA, it is flagged as overdue in this dashboard.
                SLAs help you maintain consistent service quality and generate accurate pilot metrics.
              </p>
              <div className="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2 text-default-600">
                <p>
                  <span className="font-medium">First response:</span> time from request submission to first
                  coordinator contact (status moves out of <code className="text-xs bg-default-100 px-1 rounded">pending</code>)
                </p>
                <p>
                  <span className="font-medium">Resolution:</span> time from first response to the request being
                  matched and confirmed (status reaches <code className="text-xs bg-default-100 px-1 rounded">closed</code>)
                </p>
              </div>
              <p className="text-default-500 text-xs mt-1">
                SLA windows are set in the AG81 Operating Policy. Edit them using the "Edit policy" button above.
                Set tighter SLAs for urgent care categories (e.g. medical transport) and looser ones for social
                activities.
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

      {!loading && data && summary && policy && (
        <>
          <Card className="border border-[var(--color-border)]">
            <CardBody className="py-4">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="space-y-1 text-sm">
                  <div>
                    <span className="font-semibold">First response:</span>{' '}
                    <Chip size="sm" variant="flat" color="primary">
                      {policy.first_response_hours}h
                    </Chip>{' '}
                    <span className="text-default-500">— time from submission to first coordinator contact</span>
                  </div>
                  <div>
                    <span className="font-semibold">Resolution:</span>{' '}
                    <Chip size="sm" variant="flat" color="primary">
                      {policy.resolution_hours}h
                    </Chip>{' '}
                    <span className="text-default-500">— time from first response to request being matched and confirmed</span>
                  </div>
                </div>
                <div>
                  {policy.source === 'platform_defaults' ? (
                    <Chip size="sm" variant="dot" color="warning">
                      Platform defaults — no AG81 workshop run
                    </Chip>
                  ) : (
                    <Chip size="sm" variant="dot" color="success">
                      Tenant policy applied
                    </Chip>
                  )}
                </div>
              </div>
            </CardBody>
          </Card>

          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <Card className="border border-danger-200 bg-danger-50/30 dark:bg-danger-900/10">
              <CardBody className="py-3 text-center">
                <p className="text-xs text-default-500">First-response breached</p>
                <p className="text-2xl font-bold text-danger">{summary.first_response_breached}</p>
              </CardBody>
            </Card>
            <Card className="border border-warning-200 bg-warning-50/30 dark:bg-warning-900/10">
              <CardBody className="py-3 text-center">
                <p className="text-xs text-default-500">First-response at risk</p>
                <p className="text-2xl font-bold text-warning">{summary.first_response_at_risk}</p>
              </CardBody>
            </Card>
            <Card className="border border-danger-200 bg-danger-50/30 dark:bg-danger-900/10">
              <CardBody className="py-3 text-center">
                <p className="text-xs text-default-500">Resolution breached</p>
                <p className="text-2xl font-bold text-danger">{summary.resolution_breached}</p>
              </CardBody>
            </Card>
            <Card className="border border-warning-200 bg-warning-50/30 dark:bg-warning-900/10">
              <CardBody className="py-3 text-center">
                <p className="text-xs text-default-500">Resolution at risk</p>
                <p className="text-2xl font-bold text-warning">{summary.resolution_at_risk}</p>
              </CardBody>
            </Card>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <Card className="border border-[var(--color-border)]">
              <CardBody className="py-3 text-center">
                <p className="text-xs text-default-500">Pending</p>
                <p className="text-xl font-semibold">{summary.pending}</p>
              </CardBody>
            </Card>
            <Card className="border border-[var(--color-border)]">
              <CardBody className="py-3 text-center">
                <p className="text-xs text-default-500">In progress</p>
                <p className="text-xl font-semibold">{summary.in_progress}</p>
              </CardBody>
            </Card>
            <Card className="border border-success-200 bg-success-50/30 dark:bg-success-900/10">
              <CardBody className="py-3 text-center">
                <p className="text-xs text-default-500">Resolved &lt;24h (recent)</p>
                <p className="text-xl font-semibold text-success">
                  {summary.resolved_within_window_24h}
                </p>
              </CardBody>
            </Card>
          </div>

          <Card className="border border-[var(--color-border)]">
            <CardHeader className="flex flex-wrap items-center justify-between gap-2 pb-2">
              <div>
                <p className="text-sm font-semibold">Open help requests</p>
                <p className="text-xs text-default-500">
                  {data.open_requests.length} open · sorted breached first
                </p>
              </div>
              <Tabs
                size="sm"
                aria-label="Bucket filter"
                selectedKey={bucketFilter}
                onSelectionChange={(key) => setBucketFilter(key as 'all' | Bucket)}
              >
                <Tab key="all" title={`All (${data.open_requests.length})`} />
                <Tab key="breached" title={`Breached (${bucketCounts.breached})`} />
                <Tab key="at_risk" title={`At risk (${bucketCounts.at_risk})`} />
                <Tab key="on_track" title={`On track (${bucketCounts.on_track})`} />
              </Tabs>
            </CardHeader>
            <CardBody className="pt-0">
              {filteredOpen.length === 0 ? (
                <div className="flex items-center gap-2 py-6 text-default-500 text-sm">
                  <CheckCircle2 size={16} className="text-success" />
                  No open help requests in this bucket.
                </div>
              ) : (
                <Table aria-label="Open help requests" removeWrapper>
                  <TableHeader>
                    <TableColumn>Request</TableColumn>
                    <TableColumn><Abbr term="SLA">SLA</Abbr></TableColumn>
                    <TableColumn>Bucket</TableColumn>
                    <TableColumn>Status</TableColumn>
                    <TableColumn>Created</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {filteredOpen.map((req) => (
                      <TableRow key={req.id}>
                        <TableCell>
                          <div className="max-w-md">
                            <p className="text-sm line-clamp-2">{req.what}</p>
                            <p className="text-xs text-default-500 mt-0.5">
                              When: {req.when_needed} · user #{req.user_id} · age{' '}
                              {fmtHours(req.age_hours)}
                            </p>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="text-xs space-y-0.5">
                            <div className="font-medium">{DIMENSION_LABELS[req.sla_dimension]}</div>
                            <div className="text-default-500">
                              Target {req.sla_target_hours}h
                            </div>
                            {req.bucket === 'breached' ? (
                              <div className="text-danger font-medium">
                                Over by {fmtHours(req.sla_overage_hours)}
                              </div>
                            ) : (
                              <div className="text-default-500">
                                {fmtHours(req.sla_remaining_hours)} left
                              </div>
                            )}
                          </div>
                        </TableCell>
                        <TableCell>
                          <Chip size="sm" variant="flat" color={BUCKET_COLORS[req.bucket]}>
                            {BUCKET_LABELS[req.bucket]}
                          </Chip>
                        </TableCell>
                        <TableCell>
                          <Chip size="sm" variant="dot">
                            {req.status}
                          </Chip>
                        </TableCell>
                        <TableCell>
                          <span className="text-xs text-default-500">
                            {new Date(req.created_at).toLocaleDateString()}
                          </span>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardBody>
          </Card>

          {data.recently_resolved.length > 0 && (
            <Card className="border border-[var(--color-border)]">
              <CardHeader className="pb-2">
                <p className="text-sm font-semibold">Recently resolved (last 72h)</p>
              </CardHeader>
              <CardBody className="pt-0">
                <Table aria-label="Recently resolved help requests" removeWrapper>
                  <TableHeader>
                    <TableColumn>Request</TableColumn>
                    <TableColumn>Turnaround</TableColumn>
                    <TableColumn>Within <Abbr term="SLA">SLA</Abbr></TableColumn>
                    <TableColumn>Resolved</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {data.recently_resolved.map((req) => (
                      <TableRow key={req.id}>
                        <TableCell>
                          <p className="text-sm line-clamp-1 max-w-md">{req.what}</p>
                          <p className="text-xs text-default-500 mt-0.5">user #{req.user_id}</p>
                        </TableCell>
                        <TableCell>
                          <span className="text-sm">
                            {req.turnaround_hours !== undefined
                              ? fmtHours(req.turnaround_hours)
                              : '—'}
                          </span>
                        </TableCell>
                        <TableCell>
                          {req.within_resolution_sla === undefined ? (
                            <span className="text-default-400">—</span>
                          ) : req.within_resolution_sla ? (
                            <Chip size="sm" variant="flat" color="success">
                              Yes
                            </Chip>
                          ) : (
                            <Chip size="sm" variant="flat" color="danger">
                              No
                            </Chip>
                          )}
                        </TableCell>
                        <TableCell>
                          <span className="text-xs text-default-500">
                            {req.updated_at ? new Date(req.updated_at).toLocaleString() : '—'}
                          </span>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardBody>
            </Card>
          )}

          <Divider />
          <p className="text-xs text-default-500">
            Report generated {new Date(data.generated_at).toLocaleString()}. <Abbr term="SLA">SLA</Abbr> proxy:
            first-response = status moves out of <code>pending</code>; resolution = status reaches{' '}
            <code>closed</code>. Adjust <Abbr term="SLA">SLA</Abbr> windows in AG81 operating policy.
          </p>
        </>
      )}
    </div>
  );
}
