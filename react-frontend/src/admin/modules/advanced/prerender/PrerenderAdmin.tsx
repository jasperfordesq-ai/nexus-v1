// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Prerender Admin — control panel for the bot-only prerender engine.
 *
 * Tabs:
 *   Overview   — health, summary, last run, force-refresh actions
 *   Inventory  — every rendered HTML file with age/staleness, deep inspect drawer
 *   Coverage   — per-tenant expected-vs-rendered route matrix
 *   Jobs       — queued/running/completed force-refresh job history
 *   Events     — JSONL event stream (start/supersede/success/fail/partial)
 *   Failures   — recent failed cache paths inside the backoff window
 *
 * Read endpoints require admin; enqueue/cancel require super_admin and are
 * also blocked client-side via the disabled state derived from useAuth().
 */

import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import {
  Card, CardBody, CardHeader, Button, Tabs, Tab, Chip, Spinner,
  Input, Switch, Select, SelectItem, Table, TableHeader, TableColumn,
  TableBody, TableRow, TableCell, Modal, ModalContent, ModalHeader,
  ModalBody, ModalFooter, useDisclosure, Tooltip, Divider, Code,
} from '@heroui/react';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Play from 'lucide-react/icons/play';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import Clock from 'lucide-react/icons/clock';
import Search from 'lucide-react/icons/search';
import ExternalLink from 'lucide-react/icons/external-link';
import HardDrive from 'lucide-react/icons/hard-drive';
import LayoutGrid from 'lucide-react/icons/layout-grid';
import Briefcase from 'lucide-react/icons/briefcase';
import Activity from 'lucide-react/icons/activity';
import AlertOctagon from 'lucide-react/icons/octagon-alert';
import StopCircle from 'lucide-react/icons/circle-stop';
import { usePageTitle } from '@/hooks';
import { useToast, useAuth, usePusherOptional } from '@/contexts';
import { PageHeader } from '../../../components';
import {
  adminPrerender,
  type PrerenderSummary,
  type PrerenderInventoryItem,
  type PrerenderCoverageRow,
  type PrerenderJob,
  type PrerenderEvent,
  type PrerenderFailure,
  type PrerenderInspect,
} from '../../../api/adminApi';

// ─── helpers ───────────────────────────────────────────────────────────────

function formatBytes(n: number): string {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / 1024 / 1024).toFixed(2)} MB`;
}

function formatAge(seconds: number | null | undefined): string {
  if (seconds == null) return '—';
  if (seconds < 60) return `${seconds}s`;
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h`;
  return `${Math.floor(seconds / 86400)}d`;
}

function formatTs(ts: number | string | null | undefined): string {
  if (!ts) return '—';
  const d = typeof ts === 'number' ? new Date(ts * 1000) : new Date(ts);
  if (Number.isNaN(d.getTime())) return String(ts);
  return d.toLocaleString();
}

function stalenessColor(s: 'fresh' | 'warn' | 'stale'): 'success' | 'warning' | 'danger' {
  return s === 'fresh' ? 'success' : s === 'warn' ? 'warning' : 'danger';
}

function jobStatusColor(s: PrerenderJob['status']): 'default' | 'primary' | 'success' | 'warning' | 'danger' {
  switch (s) {
    case 'queued':    return 'default';
    case 'claimed':
    case 'running':   return 'primary';
    case 'succeeded': return 'success';
    case 'partial':   return 'warning';
    case 'failed':    return 'danger';
    case 'cancelled': return 'default';
  }
}

// ─── main component ────────────────────────────────────────────────────────

/**
 * Shared cross-tab signal that a job state changed in realtime. Tabs key
 * their reload effect off this, replacing the old polling.
 */
function useJobUpdates(): { lastUpdate: number; live: boolean } {
  const pusher = usePusherOptional();
  const [lastUpdate, setLastUpdate] = useState(0);
  const [live, setLive] = useState(false);

  useEffect(() => {
    if (!pusher?.client) return;
    const ch = pusher.client.subscribe('private-admin-prerender');
    const onSub = () => setLive(true);
    const onErr = () => setLive(false);
    const onEvent = () => setLastUpdate(Date.now());
    ch.bind('pusher:subscription_succeeded', onSub);
    ch.bind('pusher:subscription_error', onErr);
    ch.bind('job.updated', onEvent);
    return () => {
      ch.unbind('job.updated', onEvent);
      ch.unbind('pusher:subscription_succeeded', onSub);
      ch.unbind('pusher:subscription_error', onErr);
      try { pusher.client?.unsubscribe('private-admin-prerender'); } catch { /* noop */ }
    };
  }, [pusher]);

  return { lastUpdate, live };
}

export function PrerenderAdmin() {
  usePageTitle('Prerender Engine');
  const toast = useToast();
  const { user } = useAuth();
  const isSuperAdmin = Boolean(
    user?.is_super_admin || user?.is_god || user?.role === 'super_admin',
  );

  const [tab, setTab] = useState<string>('overview');
  const [coverageFilter, setCoverageFilter] = useState<string | null>(null);
  const { lastUpdate, live } = useJobUpdates();

  return (
    <div>
      <PageHeader
        title="Prerender Engine"
        description="Bot-only server-rendered snapshots. Monitor coverage, detect staleness, force refreshes."
      />

      <div className="flex justify-end mb-2">
        <Chip
          size="sm"
          variant="flat"
          color={live ? 'success' : 'default'}
          startContent={<span className={`inline-block w-2 h-2 rounded-full ${live ? 'bg-success animate-pulse' : 'bg-default-400'}`} />}
        >
          {live ? 'Live updates connected' : 'Polling fallback'}
        </Chip>
      </div>

      <Tabs
        aria-label="Prerender tabs"
        selectedKey={tab}
        onSelectionChange={(k) => setTab(String(k))}
        variant="underlined"
        classNames={{ tabList: 'mb-4' }}
      >
        <Tab
          key="overview"
          title={<span className="flex items-center gap-2"><Activity size={16} />Overview</span>}
        >
          <OverviewTab isSuperAdmin={isSuperAdmin} toast={toast} lastUpdate={lastUpdate} />
        </Tab>
        <Tab
          key="inventory"
          title={<span className="flex items-center gap-2"><HardDrive size={16} />Inventory</span>}
        >
          <InventoryTab presetTenant={coverageFilter} onPresetConsumed={() => setCoverageFilter(null)} />
        </Tab>
        <Tab
          key="coverage"
          title={<span className="flex items-center gap-2"><LayoutGrid size={16} />Coverage</span>}
        >
          <CoverageTab
            isSuperAdmin={isSuperAdmin}
            toast={toast}
            onDrillDown={(slug) => { setCoverageFilter(slug); setTab('inventory'); }}
          />
        </Tab>
        <Tab
          key="jobs"
          title={<span className="flex items-center gap-2"><Briefcase size={16} />Jobs</span>}
        >
          <JobsTab isSuperAdmin={isSuperAdmin} toast={toast} lastUpdate={lastUpdate} live={live} />
        </Tab>
        <Tab
          key="events"
          title={<span className="flex items-center gap-2"><Activity size={16} />Events</span>}
        >
          <EventsTab />
        </Tab>
        <Tab
          key="failures"
          title={<span className="flex items-center gap-2"><AlertOctagon size={16} />Failures</span>}
        >
          <FailuresTab />
        </Tab>
      </Tabs>
    </div>
  );
}

export default PrerenderAdmin;

// ─── Overview ──────────────────────────────────────────────────────────────

interface ToastShape {
  success: (m: string) => void;
  error: (m: string) => void;
}

function OverviewTab({ isSuperAdmin, toast, lastUpdate }: { isSuperAdmin: boolean; toast: ToastShape; lastUpdate: number }) {
  const [summary, setSummary] = useState<PrerenderSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [enqueuing, setEnqueuing] = useState(false);
  const [tenantSlug, setTenantSlug] = useState('');
  const [routes, setRoutes] = useState('');
  const [force, setForce] = useState(false);
  const [dryRun, setDryRun] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    adminPrerender.getSummary()
      .then((res) => { if (res.data) setSummary(res.data as PrerenderSummary); })
      .catch(() => toast.error('Failed to load summary'))
      .finally(() => setLoading(false));
  }, [toast]);

  useEffect(() => { load(); }, [load]);
  // Reload on Pusher signal; fall back to 30s poll if realtime fails.
  useEffect(() => { if (lastUpdate > 0) load(); }, [lastUpdate, load]);
  useEffect(() => {
    const id = setInterval(load, 30_000);
    return () => clearInterval(id);
  }, [load]);

  const enqueue = async () => {
    setEnqueuing(true);
    try {
      const res = await adminPrerender.enqueueJob({
        tenant_slug: tenantSlug.trim() || undefined,
        routes: routes.trim() || undefined,
        force,
        dry_run: dryRun,
      });
      if (res.data) {
        toast.success(`Queued job #${res.data.job_id}`);
        setTenantSlug('');
        setRoutes('');
        load();
      }
    } catch {
      toast.error('Failed to enqueue job');
    } finally {
      setEnqueuing(false);
    }
  };

  if (loading && !summary) {
    return <div className="flex justify-center py-8"><Spinner /></div>;
  }
  if (!summary) return <p className="text-default-500">No summary available.</p>;

  const healthBadge = !summary.cache_readable
    ? <Chip color="danger" variant="flat">Cache unreachable</Chip>
    : summary.missing_count > 0
      ? <Chip color="warning" variant="flat">{summary.missing_count} missing</Chip>
      : summary.stale_count > 0
        ? <Chip color="warning" variant="flat">{summary.stale_count} stale</Chip>
        : <Chip color="success" variant="flat">Healthy</Chip>;

  return (
    <div className="space-y-4">
      {/* KPI grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <KpiCard label="Coverage" value={`${summary.coverage_pct}%`} hint={`${summary.total_snapshots} / ${summary.expected_count}`} />
        <KpiCard label="Missing" value={summary.missing_count} hint={`${summary.tenant_count} tenants × ${summary.expected_routes.length} routes`} tone={summary.missing_count > 0 ? 'warning' : 'default'} />
        <KpiCard label="Age stale (>14d)" value={summary.stale_count} hint={`${summary.warn_count} aging (>7d)`} tone={summary.stale_count > 0 ? 'warning' : 'default'} />
        <KpiCard label="Content stale" value={summary.content_stale_count} hint="source content newer than snapshot" tone={summary.content_stale_count > 0 ? 'warning' : 'default'} />
        <KpiCard label="Asset-broken" value={summary.asset_invalid_count} hint="references dead /assets/*" tone={summary.asset_invalid_count > 0 ? 'danger' : 'default'} />
        <KpiCard label="Cache size" value={formatBytes(summary.total_size_bytes)} hint={`oldest ${formatAge(summary.oldest_age_s)} • newest ${formatAge(summary.newest_age_s)}`} />
        <KpiCard label="Queued jobs" value={summary.queued_jobs} hint={`${summary.active_jobs} active`} tone={summary.active_jobs > 0 ? 'primary' : 'default'} />
        <KpiCard label="Recent failures" value={summary.recent_failures} hint="in 6h backoff window" tone={summary.recent_failures > 0 ? 'danger' : 'default'} />
        <KpiCard label="Build commit" value={summary.build_commit || '—'} hint={summary.last_event_at ? `event ${formatTs(summary.last_event_at)}` : 'no events'} />
        <KpiCard label="Status" value={healthBadge} hint={summary.cache_path} />
        <KpiCard
          label="Metrics"
          value={<a href="/api/v2/admin/prerender/metrics" target="_blank" rel="noopener noreferrer" className="text-primary text-sm hover:underline">/metrics</a>}
          hint="Prometheus text format"
        />
      </div>

      {/* Last run */}
      {summary.last_run && (
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold">Last completed run</h3>
          </CardHeader>
          <CardBody>
            <Code className="text-xs whitespace-pre-wrap block">
              {JSON.stringify(summary.last_run, null, 2)}
            </Code>
          </CardBody>
        </Card>
      )}

      {/* Force refresh */}
      <Card shadow="sm">
        <CardHeader className="flex items-center justify-between">
          <h3 className="text-lg font-semibold flex items-center gap-2">
            <Play size={18} />Force refresh
          </h3>
          {!isSuperAdmin && (
            <Chip color="warning" variant="flat" size="sm">Super admin only</Chip>
          )}
        </CardHeader>
        <CardBody className="gap-3">
          <p className="text-sm text-default-500">
            Queue a re-render. Leave both fields empty to refresh every tenant and route.
            The host processor picks the job up within ~60 seconds.
          </p>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <Input
              label="Tenant slug"
              placeholder="e.g. hour-timebank (blank = all)"
              variant="bordered"
              value={tenantSlug}
              onValueChange={setTenantSlug}
              isDisabled={!isSuperAdmin}
            />
            <Input
              label="Routes"
              placeholder="/about,/blog (blank = all)"
              variant="bordered"
              value={routes}
              onValueChange={setRoutes}
              isDisabled={!isSuperAdmin}
            />
          </div>
          <div className="flex items-center gap-6">
            <Switch isSelected={force} onValueChange={setForce} isDisabled={!isSuperAdmin}>
              <span className="text-sm">Force (ignore cache)</span>
            </Switch>
            <Switch isSelected={dryRun} onValueChange={setDryRun} isDisabled={!isSuperAdmin}>
              <span className="text-sm">Dry run (plan only)</span>
            </Switch>
          </div>
          <div className="flex justify-end gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={load}
              isDisabled={loading}
            >
              Refresh
            </Button>
            <Button
              color="primary"
              startContent={<Play size={16} />}
              onPress={enqueue}
              isLoading={enqueuing}
              isDisabled={!isSuperAdmin}
            >
              Queue job
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

function KpiCard({
  label,
  value,
  hint,
  tone = 'default',
}: {
  label: string;
  value: ReactNode;
  hint?: string;
  tone?: 'default' | 'primary' | 'warning' | 'danger';
}) {
  const toneClass = tone === 'warning' ? 'text-warning'
    : tone === 'danger' ? 'text-danger'
    : tone === 'primary' ? 'text-primary'
    : '';
  return (
    <Card shadow="sm" className="p-2">
      <CardBody className="py-3">
        <p className="text-xs text-default-500 uppercase tracking-wide">{label}</p>
        <p className={`text-2xl font-semibold ${toneClass} truncate`}>{value}</p>
        {hint && <p className="text-xs text-default-400 mt-1 truncate">{hint}</p>}
      </CardBody>
    </Card>
  );
}

// ─── Inventory ─────────────────────────────────────────────────────────────

function InventoryTab({ presetTenant, onPresetConsumed }: { presetTenant: string | null; onPresetConsumed: () => void }) {
  const toast = useToast();
  const [items, setItems] = useState<PrerenderInventoryItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('');
  const [stalenessFilter, setStalenessFilter] = useState<string>('all');
  const [issueFilter, setIssueFilter] = useState<string>('all');
  const [tenant, setTenant] = useState('');

  // Coverage tab may have deep-linked here with a tenant preset; consume once.
  useEffect(() => {
    if (presetTenant) {
      setTenant(presetTenant);
      onPresetConsumed();
    }
  }, [presetTenant, onPresetConsumed]);
  const [inspecting, setInspecting] = useState<PrerenderInspect | null>(null);
  const [inspectLoading, setInspectLoading] = useState(false);
  const inspectModal = useDisclosure();

  const load = useCallback(() => {
    setLoading(true);
    adminPrerender.getInventory(tenant.trim() || undefined)
      .then((res) => { if (res.data) setItems(res.data.items); })
      .catch(() => toast.error('Failed to load inventory'))
      .finally(() => setLoading(false));
  }, [tenant, toast]);

  useEffect(() => { load(); }, [load]);

  const filtered = useMemo(() => {
    let out = items;
    if (stalenessFilter !== 'all') {
      out = out.filter((i) => i.staleness === stalenessFilter);
    }
    if (issueFilter === 'content_stale') out = out.filter((i) => i.content_stale);
    if (issueFilter === 'asset_invalid') out = out.filter((i) => i.asset_issues.length > 0);
    if (filter.trim()) {
      const needle = filter.trim().toLowerCase();
      out = out.filter(
        (i) => i.route.toLowerCase().includes(needle) || i.host.toLowerCase().includes(needle),
      );
    }
    return out;
  }, [items, filter, stalenessFilter, issueFilter]);

  const openInspect = async (item: PrerenderInventoryItem) => {
    setInspectLoading(true);
    inspectModal.onOpen();
    try {
      const res = await adminPrerender.inspect(item.cache_path);
      if (res.data) setInspecting(res.data as PrerenderInspect);
    } catch {
      toast.error('Failed to inspect snapshot');
    } finally {
      setInspectLoading(false);
    }
  };

  return (
    <div className="space-y-3">
      <Card shadow="sm">
        <CardBody className="gap-3 flex-row flex-wrap items-end">
          <Input
            label="Filter"
            placeholder="route or host substring"
            variant="bordered"
            value={filter}
            onValueChange={setFilter}
            startContent={<Search size={14} />}
            className="max-w-xs"
          />
          <Select
            label="Staleness"
            variant="bordered"
            selectedKeys={[stalenessFilter]}
            onSelectionChange={(s) => setStalenessFilter(Array.from(s)[0] as string)}
            className="max-w-[180px]"
          >
            <SelectItem key="all">All</SelectItem>
            <SelectItem key="fresh">Fresh</SelectItem>
            <SelectItem key="warn">Aging</SelectItem>
            <SelectItem key="stale">Stale</SelectItem>
          </Select>
          <Select
            label="Issue"
            variant="bordered"
            selectedKeys={[issueFilter]}
            onSelectionChange={(s) => setIssueFilter(Array.from(s)[0] as string)}
            className="max-w-[180px]"
          >
            <SelectItem key="all">All</SelectItem>
            <SelectItem key="content_stale">Content drifted</SelectItem>
            <SelectItem key="asset_invalid">Asset broken</SelectItem>
          </Select>
          <Input
            label="Tenant slug"
            placeholder="optional"
            variant="bordered"
            value={tenant}
            onValueChange={setTenant}
            className="max-w-xs"
          />
          <Button variant="flat" onPress={load} startContent={<RefreshCw size={14} />}>
            Reload
          </Button>
          <span className="text-sm text-default-500 ml-auto self-center">
            {filtered.length} of {items.length} snapshots
          </span>
        </CardBody>
      </Card>

      {loading ? (
        <div className="flex justify-center py-8"><Spinner /></div>
      ) : (
        <Table aria-label="Snapshot inventory" removeWrapper isStriped>
          <TableHeader>
            <TableColumn>HOST</TableColumn>
            <TableColumn>ROUTE</TableColumn>
            <TableColumn>SIZE</TableColumn>
            <TableColumn>AGE</TableColumn>
            <TableColumn>STATUS</TableColumn>
            <TableColumn>ACTIONS</TableColumn>
          </TableHeader>
          <TableBody emptyContent="No snapshots match filters">
            {filtered.slice(0, 500).map((it) => (
              <TableRow key={it.cache_path}>
                <TableCell className="text-xs">{it.host}</TableCell>
                <TableCell className="text-xs font-mono">{it.route}</TableCell>
                <TableCell className="text-xs">{formatBytes(it.size_bytes)}</TableCell>
                <TableCell className="text-xs">{formatAge(it.age_s)}</TableCell>
                <TableCell>
                  <div className="flex flex-wrap gap-1">
                    <Chip color={stalenessColor(it.staleness)} variant="flat" size="sm">
                      {it.staleness}
                    </Chip>
                    {it.content_stale && (
                      <Tooltip content={it.content_stale_reason ?? 'content drifted'}>
                        <Chip color="warning" variant="flat" size="sm">content</Chip>
                      </Tooltip>
                    )}
                    {it.asset_issues.length > 0 && (
                      <Tooltip content={it.asset_issues.join(', ')}>
                        <Chip color="danger" variant="flat" size="sm">asset</Chip>
                      </Tooltip>
                    )}
                  </div>
                </TableCell>
                <TableCell>
                  <div className="flex gap-1">
                    <Tooltip content="Inspect">
                      <Button isIconOnly size="sm" variant="light" onPress={() => openInspect(it)}>
                        <Search size={14} />
                      </Button>
                    </Tooltip>
                    <Tooltip content="Open rendered URL">
                      <Button
                        isIconOnly
                        size="sm"
                        variant="light"
                        as="a"
                        href={`https://${it.host}${it.route === '/' ? '' : it.route}`}
                        target="_blank"
                        rel="noopener noreferrer"
                      >
                        <ExternalLink size={14} />
                      </Button>
                    </Tooltip>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}

      <Modal isOpen={inspectModal.isOpen} onOpenChange={inspectModal.onOpenChange} size="3xl" scrollBehavior="inside">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>Snapshot inspection</ModalHeader>
              <ModalBody>
                {inspectLoading || !inspecting ? (
                  <Spinner />
                ) : (
                  <div className="space-y-3 text-sm">
                    <div className="grid grid-cols-2 gap-2">
                      <Info label="Path" value={inspecting.cache_path} mono />
                      <Info label="Size" value={formatBytes(inspecting.size_bytes)} />
                      <Info label="Age" value={formatAge(inspecting.age_s)} />
                      <Info label="Modified" value={formatTs(inspecting.mtime)} />
                      <Info label="Title" value={inspecting.title || '— missing —'} />
                      <Info label="Canonical" value={inspecting.canonical || '— missing —'} mono />
                      <Info label="Meta description" value={inspecting.meta_description || '— missing —'} />
                      <Info label="H1 (count)" value={`${inspecting.h1_texts.length}${inspecting.h1_texts.length > 0 ? ` — "${inspecting.h1_texts[0]}"` : ''}`} />
                    </div>
                    <Divider />
                    <div>
                      <p className="font-semibold mb-1">Flags</p>
                      <div className="flex flex-wrap gap-2">
                        {Object.entries(inspecting.flags).map(([k, v]) => {
                          const isBad = k === 'multiple_h1';
                          const good = isBad ? !v : v;
                          return (
                            <Chip key={k} size="sm" color={good ? 'success' : 'danger'} variant="flat">
                              {good ? '✓' : '✗'} {k}
                            </Chip>
                          );
                        })}
                      </div>
                    </div>
                    {inspecting.parse_warnings.length > 0 && (
                      <>
                        <Divider />
                        <div>
                          <p className="font-semibold mb-1 text-warning">HTML parse warnings</p>
                          <Code className="text-xs whitespace-pre-wrap block">
                            {inspecting.parse_warnings.join('\n')}
                          </Code>
                        </div>
                      </>
                    )}
                    <Divider />
                    <div>
                      <p className="font-semibold mb-1">
                        JSON-LD ({inspecting.json_ld.blocks_count} blocks
                        {inspecting.json_ld.all_valid ? ', all valid' : ', INVALID PRESENT'})
                      </p>
                      {inspecting.json_ld.blocks.length === 0 ? (
                        <p className="text-xs text-default-400">No structured data</p>
                      ) : (
                        <div className="space-y-1">
                          {inspecting.json_ld.blocks.map((b, i) => (
                            <div key={i} className="text-xs flex gap-2 items-center">
                              <Chip size="sm" color={b.valid ? 'success' : 'danger'} variant="flat">
                                {b.valid ? '✓' : '✗'}
                              </Chip>
                              <span className="font-mono">{b.size}B</span>
                              <span>{b.types.join(', ') || '(no @type)'}</span>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                    {Object.keys(inspecting.og_tags).length > 0 && (
                      <>
                        <Divider />
                        <div>
                          <p className="font-semibold mb-1">Open Graph</p>
                          <div className="text-xs space-y-0.5">
                            {Object.entries(inspecting.og_tags).map(([k, v]) => (
                              <div key={k} className="flex gap-2">
                                <span className="font-mono text-default-500">{k}</span>
                                <span className="truncate">{v}</span>
                              </div>
                            ))}
                          </div>
                        </div>
                      </>
                    )}
                    <Divider />
                    <div>
                      <p className="font-semibold mb-1">
                        Asset references ({inspecting.asset_refs.length})
                        {inspecting.asset_issues.length > 0 && (
                          <Chip color="danger" variant="flat" size="sm" className="ml-2">
                            {inspecting.asset_issues.length} dead
                          </Chip>
                        )}
                      </p>
                      <Code className="text-xs whitespace-pre-wrap block">
                        {inspecting.asset_refs.map((r) => `${inspecting.asset_issues.includes(r) ? '✗ ' : '  '}${r}`).join('\n') || '(none)'}
                      </Code>
                    </div>
                    <Divider />
                    <div>
                      <p className="font-semibold mb-1">HTML preview (first 12KB, scripts stripped)</p>
                      <Code className="text-xs whitespace-pre-wrap block max-h-96 overflow-auto">
                        {inspecting.preview}
                      </Code>
                    </div>
                  </div>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="light" onPress={onClose}>Close</Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

function Info({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
  return (
    <div>
      <p className="text-xs text-default-500">{label}</p>
      <p className={`text-sm ${mono ? 'font-mono' : ''} break-all`}>{value}</p>
    </div>
  );
}

// ─── Coverage ──────────────────────────────────────────────────────────────

function CoverageTab({ isSuperAdmin, toast, onDrillDown }: { isSuperAdmin: boolean; toast: ToastShape; onDrillDown: (slug: string) => void }) {
  const [rows, setRows] = useState<PrerenderCoverageRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [enqueuingFor, setEnqueuingFor] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    adminPrerender.getCoverage()
      .then((r) => { if (r.data) setRows(r.data.rows); })
      .catch(() => toast.error('Failed to load coverage'))
      .finally(() => setLoading(false));
  }, [toast]);

  useEffect(() => { load(); }, [load]);

  const refreshTenant = async (slug: string) => {
    setEnqueuingFor(slug);
    try {
      const res = await adminPrerender.enqueueJob({ tenant_slug: slug, force: true });
      if (res.data) toast.success(`Queued #${res.data.job_id} for ${slug}`);
    } catch {
      toast.error('Failed to enqueue');
    } finally {
      setEnqueuingFor(null);
    }
  };

  if (loading) return <div className="flex justify-center py-8"><Spinner /></div>;

  return (
    <Table aria-label="Coverage" removeWrapper isStriped>
      <TableHeader>
        <TableColumn>TENANT</TableColumn>
        <TableColumn>HOST</TableColumn>
        <TableColumn>COVERAGE</TableColumn>
        <TableColumn>STALE</TableColumn>
        <TableColumn>ASSET</TableColumn>
        <TableColumn>MISSING</TableColumn>
        <TableColumn>ACTIONS</TableColumn>
      </TableHeader>
      <TableBody emptyContent="No tenants">
        {rows.map((r) => {
          const pct = r.expected > 0 ? Math.round((r.rendered / r.expected) * 100) : 0;
          const color = pct >= 95 ? 'success' : pct >= 70 ? 'warning' : 'danger';
          return (
            <TableRow key={r.slug}>
              <TableCell>
                <button
                  className="font-medium text-primary hover:underline"
                  onClick={() => onDrillDown(r.slug)}
                  title="Open in inventory"
                >
                  {r.slug}
                </button>
              </TableCell>
              <TableCell className="text-xs">{r.host}</TableCell>
              <TableCell>
                <Chip color={color} variant="flat" size="sm">
                  {r.rendered}/{r.expected} ({pct}%)
                </Chip>
              </TableCell>
              <TableCell>
                {r.stale_routes.length > 0 ? (
                  <Tooltip content={r.stale_routes.join(', ')}>
                    <button onClick={() => onDrillDown(r.slug)} className="cursor-pointer">
                      <Chip color="warning" variant="flat" size="sm">{r.stale_routes.length}</Chip>
                    </button>
                  </Tooltip>
                ) : <span className="text-default-400 text-xs">—</span>}
              </TableCell>
              <TableCell>
                {r.asset_invalid_routes.length > 0 ? (
                  <Tooltip content={r.asset_invalid_routes.join(', ')}>
                    <button onClick={() => onDrillDown(r.slug)} className="cursor-pointer">
                      <Chip color="danger" variant="flat" size="sm">{r.asset_invalid_routes.length}</Chip>
                    </button>
                  </Tooltip>
                ) : <span className="text-default-400 text-xs">—</span>}
              </TableCell>
              <TableCell>
                {r.missing_routes.length > 0 ? (
                  <Tooltip content={r.missing_routes.join(', ')}>
                    <button onClick={() => onDrillDown(r.slug)} className="cursor-pointer">
                      <Chip color="danger" variant="flat" size="sm">{r.missing_routes.length}</Chip>
                    </button>
                  </Tooltip>
                ) : <span className="text-default-400 text-xs">—</span>}
              </TableCell>
              <TableCell>
                <Button
                  size="sm"
                  variant="flat"
                  startContent={<Play size={14} />}
                  onPress={() => refreshTenant(r.slug)}
                  isLoading={enqueuingFor === r.slug}
                  isDisabled={!isSuperAdmin}
                >
                  Refresh
                </Button>
              </TableCell>
            </TableRow>
          );
        })}
      </TableBody>
    </Table>
  );
}

// ─── Jobs ──────────────────────────────────────────────────────────────────

function JobsTab({ isSuperAdmin, toast, lastUpdate, live }: { isSuperAdmin: boolean; toast: ToastShape; lastUpdate: number; live: boolean }) {
  const [jobs, setJobs] = useState<PrerenderJob[]>([]);
  const [loading, setLoading] = useState(true);
  const [status, setStatus] = useState<string>('all');
  const [expanded, setExpanded] = useState<PrerenderJob | null>(null);
  const detailModal = useDisclosure();

  const load = useCallback(() => {
    setLoading(true);
    adminPrerender.listJobs({ status: status === 'all' ? undefined : status, limit: 100 })
      .then((r) => { if (r.data) setJobs(r.data.items); })
      .catch(() => toast.error('Failed to load jobs'))
      .finally(() => setLoading(false));
  }, [status, toast]);

  useEffect(() => { load(); }, [load]);
  // Realtime reload on Pusher event. Polling fallback kicks in only when
  // the channel never connected — keeps live runs responsive without
  // hammering the API on quiescent days.
  useEffect(() => { if (lastUpdate > 0) load(); }, [lastUpdate, load]);
  useEffect(() => {
    if (live) return;
    const hasActive = jobs.some((j) => j.status === 'queued' || j.status === 'claimed' || j.status === 'running');
    if (!hasActive) return;
    const id = setInterval(load, 5000);
    return () => clearInterval(id);
  }, [jobs, load, live]);

  const cancelJob = async (id: number) => {
    try {
      await adminPrerender.cancelJob(id);
      toast.success(`Cancelled job #${id}`);
      load();
    } catch {
      toast.error('Could not cancel — job may have started');
    }
  };

  const openDetail = (j: PrerenderJob) => {
    setExpanded(j);
    detailModal.onOpen();
  };

  return (
    <div className="space-y-3">
      <Card shadow="sm">
        <CardBody className="flex-row gap-3 items-end">
          <Select
            label="Status"
            variant="bordered"
            selectedKeys={[status]}
            onSelectionChange={(s) => setStatus(Array.from(s)[0] as string)}
            className="max-w-[180px]"
          >
            <SelectItem key="all">All</SelectItem>
            <SelectItem key="queued">Queued</SelectItem>
            <SelectItem key="running">Running</SelectItem>
            <SelectItem key="succeeded">Succeeded</SelectItem>
            <SelectItem key="partial">Partial</SelectItem>
            <SelectItem key="failed">Failed</SelectItem>
            <SelectItem key="cancelled">Cancelled</SelectItem>
          </Select>
          <Button variant="flat" startContent={<RefreshCw size={14} />} onPress={load}>
            Reload
          </Button>
        </CardBody>
      </Card>

      {loading ? (
        <div className="flex justify-center py-8"><Spinner /></div>
      ) : (
        <Table aria-label="Jobs" removeWrapper isStriped>
          <TableHeader>
            <TableColumn>#</TableColumn>
            <TableColumn>STATUS</TableColumn>
            <TableColumn>SCOPE</TableColumn>
            <TableColumn>FLAGS</TableColumn>
            <TableColumn>RESULT</TableColumn>
            <TableColumn>QUEUED</TableColumn>
            <TableColumn>BY</TableColumn>
            <TableColumn>ACTIONS</TableColumn>
          </TableHeader>
          <TableBody emptyContent="No jobs">
            {jobs.map((j) => (
              <TableRow key={j.id}>
                <TableCell className="text-xs font-mono">{j.id}</TableCell>
                <TableCell>
                  <Chip color={jobStatusColor(j.status)} variant="flat" size="sm">
                    {j.status}
                  </Chip>
                </TableCell>
                <TableCell className="text-xs">
                  {j.tenant_slug ? <Chip size="sm" variant="flat">{j.tenant_slug}</Chip> : <span className="text-default-400">all tenants</span>}
                  {j.routes && <div className="font-mono mt-1">{j.routes}</div>}
                </TableCell>
                <TableCell>
                  <div className="flex gap-1">
                    {j.force && <Chip size="sm" color="warning" variant="flat">force</Chip>}
                    {j.dry_run && <Chip size="sm" variant="flat">dry-run</Chip>}
                  </div>
                </TableCell>
                <TableCell className="text-xs">
                  {j.rendered_count != null
                    ? `${j.rendered_count}/${j.planned_count ?? '?'}${j.invalid_count ? ` (${j.invalid_count} invalid)` : ''}${j.duration_s != null ? ` • ${j.duration_s}s` : ''}`
                    : '—'}
                </TableCell>
                <TableCell className="text-xs">{formatTs(j.queued_at)}</TableCell>
                <TableCell className="text-xs">{j.requested_by?.name || '—'}</TableCell>
                <TableCell>
                  <div className="flex gap-1">
                    <Button size="sm" variant="light" isIconOnly onPress={() => openDetail(j)}>
                      <Search size={14} />
                    </Button>
                    {j.status === 'queued' && (
                      <Button
                        size="sm"
                        variant="light"
                        color="danger"
                        isIconOnly
                        onPress={() => cancelJob(j.id)}
                        isDisabled={!isSuperAdmin}
                      >
                        <StopCircle size={14} />
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}

      <Modal isOpen={detailModal.isOpen} onOpenChange={detailModal.onOpenChange} size="3xl" scrollBehavior="inside">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>Job #{expanded?.id}</ModalHeader>
              <ModalBody>
                {expanded && (
                  <div className="space-y-2 text-sm">
                    <div className="grid grid-cols-2 gap-2">
                      <Info label="Status" value={expanded.status} />
                      <Info label="Tenant" value={expanded.tenant_slug || 'all'} />
                      <Info label="Routes" value={expanded.routes || 'all'} mono />
                      <Info label="Exit code" value={String(expanded.exit_code ?? '—')} />
                      <Info label="Duration" value={expanded.duration_s != null ? `${expanded.duration_s}s` : '—'} />
                      <Info label="Claimed by" value={expanded.claimed_by || '—'} />
                      <Info label="Queued at" value={formatTs(expanded.queued_at)} />
                      <Info label="Started at" value={formatTs(expanded.started_at)} />
                      <Info label="Finished at" value={formatTs(expanded.finished_at)} />
                      <Info label="Requested by" value={expanded.requested_by?.name || '—'} />
                    </div>
                    {expanded.error_message && (
                      <>
                        <Divider />
                        <p className="font-semibold text-danger">Error</p>
                        <Code className="text-xs whitespace-pre-wrap block">{expanded.error_message}</Code>
                      </>
                    )}
                    {expanded.log_excerpt && (
                      <>
                        <Divider />
                        <p className="font-semibold">Log (tail)</p>
                        <Code className="text-xs whitespace-pre-wrap block max-h-96 overflow-auto">
                          {expanded.log_excerpt}
                        </Code>
                      </>
                    )}
                  </div>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="light" onPress={onClose}>Close</Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

// ─── Events ────────────────────────────────────────────────────────────────

function EventsTab() {
  const toast = useToast();
  const [events, setEvents] = useState<PrerenderEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [limit, setLimit] = useState(200);

  const load = useCallback(() => {
    setLoading(true);
    adminPrerender.getEvents(limit)
      .then((r) => { if (r.data) setEvents(r.data.events); })
      .catch(() => toast.error('Failed to load events'))
      .finally(() => setLoading(false));
  }, [limit, toast]);

  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-3">
      <Card shadow="sm">
        <CardBody className="flex-row gap-3 items-end">
          <Select
            label="Limit"
            variant="bordered"
            selectedKeys={[String(limit)]}
            onSelectionChange={(s) => setLimit(parseInt(Array.from(s)[0] as string, 10))}
            className="max-w-[140px]"
          >
            <SelectItem key="50">50</SelectItem>
            <SelectItem key="200">200</SelectItem>
            <SelectItem key="500">500</SelectItem>
            <SelectItem key="2000">2000</SelectItem>
          </Select>
          <Button variant="flat" startContent={<RefreshCw size={14} />} onPress={load}>
            Reload
          </Button>
          <span className="text-sm text-default-500 ml-auto self-center">
            {events.length} events
          </span>
        </CardBody>
      </Card>

      {loading ? (
        <div className="flex justify-center py-8"><Spinner /></div>
      ) : (
        <Table aria-label="Events" removeWrapper isStriped>
          <TableHeader>
            <TableColumn>TIME</TableColumn>
            <TableColumn>EVENT</TableColumn>
            <TableColumn>COMMIT</TableColumn>
            <TableColumn>DETAILS</TableColumn>
          </TableHeader>
          <TableBody emptyContent="No events">
            {events.map((e, idx) => {
              const ev = String(e.event ?? '');
              const color =
                ev === 'success' ? 'success' :
                ev === 'partial' ? 'warning' :
                ev === 'fail' ? 'danger' :
                ev === 'supersede' ? 'warning' : 'default';
              const { ts, event, commit, pid, host, ...rest } = e;
              return (
                <TableRow key={idx}>
                  <TableCell className="text-xs">{ts ? formatTs(ts) : '—'}</TableCell>
                  <TableCell>
                    <Chip color={color} variant="flat" size="sm">{ev || '—'}</Chip>
                  </TableCell>
                  <TableCell className="text-xs font-mono">{commit || '—'}</TableCell>
                  <TableCell className="text-xs">
                    <Code className="text-xs">{JSON.stringify({ pid, host, ...rest })}</Code>
                  </TableCell>
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      )}
    </div>
  );
}

// ─── Failures ──────────────────────────────────────────────────────────────

function FailuresTab() {
  const toast = useToast();
  const [items, setItems] = useState<PrerenderFailure[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    adminPrerender.getFailures()
      .then((r) => { if (r.data) setItems(r.data.items); })
      .catch(() => toast.error('Failed to load failures'))
      .finally(() => setLoading(false));
  }, [toast]);

  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-3">
      <Card shadow="sm">
        <CardBody>
          <p className="text-sm text-default-500">
            Cache paths that failed during a recent prerender run. Routes in this list
            are skipped automatically during the failure-backoff window (default 6h).
            Use <strong>Force refresh</strong> on the Overview tab to retry immediately.
          </p>
        </CardBody>
      </Card>
      {loading ? (
        <div className="flex justify-center py-8"><Spinner /></div>
      ) : items.length === 0 ? (
        <Card shadow="sm">
          <CardBody className="text-center py-8 flex flex-col items-center gap-2">
            <CheckCircle className="text-success" size={32} />
            <p className="font-medium">No recent failures</p>
          </CardBody>
        </Card>
      ) : (
        <Table aria-label="Failures" removeWrapper isStriped>
          <TableHeader>
            <TableColumn>CACHE PATH</TableColumn>
            <TableColumn>FAILED AT</TableColumn>
            <TableColumn>AGE</TableColumn>
          </TableHeader>
          <TableBody>
            {items.map((it) => (
              <TableRow key={it.cache_path}>
                <TableCell className="text-xs font-mono">{it.cache_path}</TableCell>
                <TableCell className="text-xs">{formatTs(it.failed_at)}</TableCell>
                <TableCell className="text-xs flex items-center gap-1">
                  <Clock size={12} />{formatAge(it.age_s)}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </div>
  );
}
