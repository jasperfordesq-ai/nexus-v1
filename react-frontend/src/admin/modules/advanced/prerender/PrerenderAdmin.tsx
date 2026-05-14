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
  Checkbox,
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
import Trash from 'lucide-react/icons/trash-2';
import Bot from 'lucide-react/icons/bot';
import Gauge from 'lucide-react/icons/gauge';
import Zap from 'lucide-react/icons/zap';
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
  type PrerenderAnalytics,
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

function seoGradeColor(g: 'A' | 'B' | 'C' | 'D' | 'F'): 'success' | 'primary' | 'warning' | 'danger' | 'default' {
  switch (g) {
    case 'A': return 'success';
    case 'B': return 'primary';
    case 'C': return 'warning';
    case 'D': return 'warning';
    case 'F': return 'danger';
  }
}

function httpStatusColor(n: number): 'default' | 'success' | 'warning' | 'danger' {
  if (n === 200) return 'success';
  if (n >= 300 && n < 400) return 'default';
  if (n >= 400 && n < 500) return 'warning';
  if (n >= 500) return 'danger';
  return 'default';
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
          key="analytics"
          title={<span className="flex items-center gap-2"><Bot size={16} />Analytics</span>}
        >
          <AnalyticsTab />
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

      <FreshnessControls isSuperAdmin={isSuperAdmin} toast={toast} onActed={load} />
      <PurgeControls   isSuperAdmin={isSuperAdmin} toast={toast} onActed={load} />
    </div>
  );
}

// ─── Overview helpers: freshness + purge action cards ──────────────────────

function FreshnessControls({
  isSuperAdmin, toast, onActed,
}: { isSuperAdmin: boolean; toast: ToastShape; onActed: () => void }) {
  const [autoRecLoading, setAutoRecLoading] = useState(false);
  const [autoRecOutput, setAutoRecOutput] = useState<string>('');
  const [driftLoading, setDriftLoading] = useState(false);
  const [driftOutput, setDriftOutput] = useState<string>('');
  const [purgeLoading, setPurgeLoading] = useState(false);
  const [purgeOutput, setPurgeOutput] = useState<{ deleted_total: number; by_tenant: Record<string, string[]>; dry_run: boolean } | null>(null);

  const runPurgeUnexpected = async (apply: boolean) => {
    setPurgeLoading(true);
    setPurgeOutput(null);
    try {
      const res = await adminPrerender.purgeUnexpected(apply);
      if (res.data) {
        setPurgeOutput(res.data);
        toast.success(
          apply
            ? `Purged ${res.data.deleted_total} ungated snapshots across ${Object.keys(res.data.by_tenant).length} tenants`
            : `Dry run: ${res.data.deleted_total} snapshots would be purged`
        );
        if (apply) onActed();
      }
    } catch {
      toast.error('Purge-unexpected failed');
    } finally {
      setPurgeLoading(false);
    }
  };

  const runAutoRecache = async (apply: boolean) => {
    setAutoRecLoading(true);
    setAutoRecOutput('');
    try {
      const res = await adminPrerender.triggerAutoRecache(apply);
      if (res.data) {
        setAutoRecOutput(res.data.output || '(no output)');
        toast.success(apply ? 'Auto-recache applied' : 'Auto-recache dry-run complete');
        if (apply) onActed();
      }
    } catch {
      toast.error('Failed to trigger auto-recache');
    } finally {
      setAutoRecLoading(false);
    }
  };

  const runDriftDetect = async (apply: boolean) => {
    setDriftLoading(true);
    setDriftOutput('');
    try {
      const res = await adminPrerender.triggerDetectDrift(apply);
      if (res.data) {
        setDriftOutput(res.data.output || '(no output)');
        toast.success(apply ? 'Drift detection applied' : 'Drift dry-run complete');
        if (apply) onActed();
      }
    } catch {
      toast.error('Failed to trigger drift detection');
    } finally {
      setDriftLoading(false);
    }
  };

  return (
    <Card shadow="sm">
      <CardHeader>
        <h3 className="text-lg font-semibold flex items-center gap-2">
          <Zap size={18} />Freshness automation
        </h3>
      </CardHeader>
      <CardBody className="gap-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="space-y-2">
            <p className="font-medium flex items-center gap-2">
              <Gauge size={16} className="text-warning" />Auto-recache (TTL + content drift)
            </p>
            <p className="text-sm text-default-500">
              Scans every snapshot. Enqueues low-priority recaches for routes whose source content
              has changed (DB updated_at &gt; snapshot mtime) or whose TTL has expired. Runs on cron
              every 15–30 min; use this to trigger one immediately.
            </p>
            <div className="flex gap-2">
              <Button
                variant="flat"
                onPress={() => runAutoRecache(false)}
                isLoading={autoRecLoading}
                isDisabled={!isSuperAdmin}
                size="sm"
              >
                Dry run
              </Button>
              <Button
                color="primary"
                onPress={() => runAutoRecache(true)}
                isLoading={autoRecLoading}
                isDisabled={!isSuperAdmin}
                size="sm"
                startContent={<Play size={14} />}
              >
                Apply
              </Button>
            </div>
            {autoRecOutput && (
              <Code className="text-xs whitespace-pre-wrap block max-h-48 overflow-auto">
                {autoRecOutput}
              </Code>
            )}
          </div>

          <div className="space-y-2">
            <p className="font-medium flex items-center gap-2">
              <Search size={16} className="text-primary" />Detect drift (sitemap vs snapshots)
            </p>
            <p className="text-sm text-default-500">
              Walks each tenant&apos;s sitemap, compares &lt;lastmod&gt; against snapshot mtimes.
              Catches stale pages from code paths that bypass Eloquent observers (raw DB writes,
              migrations, queued jobs). Runs every 2 min. Enqueues HIGH-priority recaches.
            </p>
            <div className="flex gap-2">
              <Button
                variant="flat"
                onPress={() => runDriftDetect(false)}
                isLoading={driftLoading}
                isDisabled={!isSuperAdmin}
                size="sm"
              >
                Dry run
              </Button>
              <Button
                color="primary"
                onPress={() => runDriftDetect(true)}
                isLoading={driftLoading}
                isDisabled={!isSuperAdmin}
                size="sm"
                startContent={<Play size={14} />}
              >
                Apply
              </Button>
            </div>
            {driftOutput && (
              <Code className="text-xs whitespace-pre-wrap block max-h-48 overflow-auto">
                {driftOutput}
              </Code>
            )}
          </div>
        </div>

        <Divider className="my-2" />

        <div className="space-y-2">
          <p className="font-medium flex items-center gap-2">
            <Trash size={16} className="text-danger" />Purge ungated snapshots (per-tenant 404 cleanup)
          </p>
          <p className="text-sm text-default-500">
            Sweep snapshots whose route isn&apos;t in a tenant&apos;s expected set. Common after toggling
            a feature off, or for the one-time cleanup of 404s left from the era when every tenant
            was prerendered against the global route list regardless of feature flags. Dynamic
            content routes (blog posts, listings, events, etc.) are left alone — only static routes
            that shouldn&apos;t exist for the tenant get deleted.
          </p>
          <div className="flex gap-2">
            <Button
              variant="flat"
              onPress={() => runPurgeUnexpected(false)}
              isLoading={purgeLoading}
              isDisabled={!isSuperAdmin}
              size="sm"
            >
              Dry run
            </Button>
            <Button
              color="danger"
              onPress={() => runPurgeUnexpected(true)}
              isLoading={purgeLoading}
              isDisabled={!isSuperAdmin}
              size="sm"
              startContent={<Trash size={14} />}
            >
              Apply
            </Button>
          </div>
          {purgeOutput && (
            <div className="space-y-1">
              <p className="text-sm font-medium">
                {purgeOutput.dry_run ? 'Would delete' : 'Deleted'} {purgeOutput.deleted_total}{' '}
                snapshots across {Object.keys(purgeOutput.by_tenant).length} tenants
              </p>
              {Object.keys(purgeOutput.by_tenant).length > 0 && (
                <Code className="text-xs whitespace-pre-wrap block max-h-64 overflow-auto">
                  {Object.entries(purgeOutput.by_tenant)
                    .map(([slug, routes]) => `${slug} (${routes.length}):\n  ${routes.join('\n  ')}`)
                    .join('\n\n')}
                </Code>
              )}
            </div>
          )}
        </div>
      </CardBody>
    </Card>
  );
}

function PurgeControls({
  isSuperAdmin, toast, onActed,
}: { isSuperAdmin: boolean; toast: ToastShape; onActed: () => void }) {
  const [pattern, setPattern] = useState('');
  const [tenant, setTenant] = useState('');
  const [dryRun, setDryRun] = useState(true);
  const [recache, setRecache] = useState(true);
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<{ deleted_count: number; deleted: string[]; dry_run: boolean } | null>(null);

  const submit = async () => {
    if (!pattern.trim()) {
      toast.error('Pattern is required (e.g. /blog/*)');
      return;
    }
    setLoading(true);
    setResult(null);
    try {
      const res = await adminPrerender.purge({
        pattern: pattern.trim(),
        tenant_slug: tenant.trim() || undefined,
        dry_run: dryRun,
        recache,
      });
      if (res.data) {
        setResult(res.data);
        toast.success(
          dryRun
            ? `Dry run: ${res.data.deleted_count} snapshots would be purged`
            : `Purged ${res.data.deleted_count} snapshots${res.data.recache_job_id ? ` (job #${res.data.recache_job_id})` : ''}`
        );
        if (!dryRun) onActed();
      }
    } catch {
      toast.error('Purge failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Card shadow="sm">
      <CardHeader>
        <h3 className="text-lg font-semibold flex items-center gap-2">
          <Trash size={18} />Wildcard cache purge
        </h3>
      </CardHeader>
      <CardBody className="gap-3">
        <p className="text-sm text-default-500">
          Delete snapshots matching a glob pattern. Use <code>*</code> for one path segment,
          <code className="ml-1">**</code> for any descendant. Examples: <code>/blog/*</code>,
          <code className="ml-1">/listings/**</code>, <code className="ml-1">/</code> (homepage only).
        </p>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <Input
            label="Pattern"
            placeholder="/blog/* or /listings/**"
            variant="bordered"
            value={pattern}
            onValueChange={setPattern}
            isDisabled={!isSuperAdmin}
          />
          <Input
            label="Tenant slug"
            placeholder="optional — leave blank for all"
            variant="bordered"
            value={tenant}
            onValueChange={setTenant}
            isDisabled={!isSuperAdmin}
          />
        </div>
        <div className="flex items-center gap-6">
          <Switch isSelected={dryRun} onValueChange={setDryRun} isDisabled={!isSuperAdmin}>
            <span className="text-sm">Dry run</span>
          </Switch>
          <Switch isSelected={recache} onValueChange={setRecache} isDisabled={!isSuperAdmin}>
            <span className="text-sm">Auto-recache after purge</span>
          </Switch>
        </div>
        <div className="flex justify-end">
          <Button
            color={dryRun ? 'primary' : 'danger'}
            startContent={<Trash size={16} />}
            onPress={submit}
            isLoading={loading}
            isDisabled={!isSuperAdmin}
          >
            {dryRun ? 'Preview purge' : 'Purge now'}
          </Button>
        </div>
        {result && (
          <div className="space-y-1">
            <p className="text-sm font-medium">
              {result.dry_run ? 'Would delete' : 'Deleted'} {result.deleted_count} snapshots
            </p>
            {result.deleted.length > 0 && (
              <Code className="text-xs whitespace-pre-wrap block max-h-48 overflow-auto">
                {result.deleted.join('\n')}
              </Code>
            )}
          </div>
        )}
      </CardBody>
    </Card>
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
  const { user } = useAuth();
  const isSuperAdmin = Boolean(user?.is_super_admin || user?.is_god || user?.role === 'super_admin');
  const [items, setItems] = useState<PrerenderInventoryItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('');
  const [stalenessFilter, setStalenessFilter] = useState<string>('all');
  const [issueFilter, setIssueFilter] = useState<string>('all');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [tenant, setTenant] = useState('');
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [bulkLoading, setBulkLoading] = useState(false);

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
    if (statusFilter === '200')      out = out.filter((i) => i.http_status === 200);
    else if (statusFilter === 'non-200') out = out.filter((i) => i.http_status !== 200);
    if (filter.trim()) {
      const needle = filter.trim().toLowerCase();
      out = out.filter(
        (i) => i.route.toLowerCase().includes(needle) || i.host.toLowerCase().includes(needle),
      );
    }
    return out;
  }, [items, filter, stalenessFilter, issueFilter, statusFilter]);

  /**
   * Bulk-recache the currently-selected rows. Groups by host → tenant so each
   * tenant gets a single enqueue with a comma-joined route list at NORMAL
   * priority (user-initiated, not background).
   */
  const bulkRecache = async () => {
    if (selected.size === 0) return;
    setBulkLoading(true);
    try {
      // Group selected items by host (resolves to tenant via the inventory rows).
      const byHost = new Map<string, { tenantId: number | null; routes: string[] }>();
      const itemMap = new Map(filtered.map((i) => [i.cache_path, i] as const));
      // We need tenant_id, but the inventory row doesn't carry it. Use the
      // invalidate webhook which takes (tenant_id, routes[]). To resolve
      // tenant_id from host, look up via the coverage API on first use.
      const coverage = await adminPrerender.getCoverage();
      const hostToTenantId = new Map(
        (coverage.data?.rows ?? []).map((r) => [r.host, r.tenant_id] as const),
      );

      for (const cachePath of selected) {
        const it = itemMap.get(cachePath);
        if (!it) continue;
        const tenantId = hostToTenantId.get(it.host) ?? null;
        const slot = byHost.get(it.host) ?? { tenantId, routes: [] };
        slot.routes.push(it.route);
        byHost.set(it.host, slot);
      }

      let invalidated = 0;
      for (const [, slot] of byHost) {
        if (slot.tenantId == null || slot.routes.length === 0) continue;
        const res = await adminPrerender.invalidate({
          tenant_id: slot.tenantId,
          routes: slot.routes,
          recache: true,
        });
        invalidated += res.data?.invalidated ?? 0;
      }

      toast.success(`Invalidated ${invalidated} snapshots and queued recache`);
      setSelected(new Set());
      load();
    } catch {
      toast.error('Bulk recache failed');
    } finally {
      setBulkLoading(false);
    }
  };

  const toggleSelect = (cachePath: string) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(cachePath)) next.delete(cachePath);
      else next.add(cachePath);
      return next;
    });
  };
  const toggleAllVisible = () => {
    setSelected((prev) => {
      const visible = filtered.slice(0, 500).map((r) => r.cache_path);
      const allSelected = visible.every((p) => prev.has(p));
      if (allSelected) {
        const next = new Set(prev);
        for (const p of visible) next.delete(p);
        return next;
      }
      const next = new Set(prev);
      for (const p of visible) next.add(p);
      return next;
    });
  };

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
          <Select
            label="Status"
            variant="bordered"
            selectedKeys={[statusFilter]}
            onSelectionChange={(s) => setStatusFilter(Array.from(s)[0] as string)}
            className="max-w-[150px]"
          >
            <SelectItem key="all">All</SelectItem>
            <SelectItem key="200">200 only</SelectItem>
            <SelectItem key="non-200">Non-200</SelectItem>
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
            {selected.size > 0 && <> · <span className="text-primary">{selected.size} selected</span></>}
          </span>
          {selected.size > 0 && isSuperAdmin && (
            <Button
              color="primary"
              startContent={<Play size={14} />}
              onPress={bulkRecache}
              isLoading={bulkLoading}
            >
              Recache selected ({selected.size})
            </Button>
          )}
        </CardBody>
      </Card>

      {loading ? (
        <div className="flex justify-center py-8"><Spinner /></div>
      ) : (
        <Table aria-label="Snapshot inventory" removeWrapper isStriped>
          <TableHeader>
            <TableColumn>
              {isSuperAdmin ? (
                <Checkbox
                  size="sm"
                  isSelected={
                    filtered.length > 0 &&
                    filtered.slice(0, 500).every((r) => selected.has(r.cache_path))
                  }
                  onValueChange={toggleAllVisible}
                  aria-label="Select all visible"
                />
              ) : ''}
            </TableColumn>
            <TableColumn>HOST</TableColumn>
            <TableColumn>ROUTE</TableColumn>
            <TableColumn>HTTP</TableColumn>
            <TableColumn>SIZE</TableColumn>
            <TableColumn>AGE</TableColumn>
            <TableColumn>STATUS</TableColumn>
            <TableColumn>ACTIONS</TableColumn>
          </TableHeader>
          <TableBody emptyContent="No snapshots match filters">
            {filtered.slice(0, 500).map((it) => (
              <TableRow key={it.cache_path}>
                <TableCell>
                  {isSuperAdmin ? (
                    <Checkbox
                      size="sm"
                      isSelected={selected.has(it.cache_path)}
                      onValueChange={() => toggleSelect(it.cache_path)}
                      aria-label={`Select ${it.cache_path}`}
                    />
                  ) : null}
                </TableCell>
                <TableCell className="text-xs">{it.host}</TableCell>
                <TableCell className="text-xs font-mono">{it.route}</TableCell>
                <TableCell>
                  <Chip color={httpStatusColor(it.http_status)} variant="flat" size="sm">
                    {it.http_status}
                  </Chip>
                </TableCell>
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
                    {/* SEO score header — at the top so it's the first thing reviewers see. */}
                    <div className="flex items-center gap-3 p-3 rounded-lg bg-default-50 border border-default-200">
                      <div className={`text-4xl font-bold text-${seoGradeColor(inspecting.seo.grade)}`}>
                        {inspecting.seo.grade}
                      </div>
                      <div className="flex-1">
                        <p className="text-sm text-default-500">SEO score</p>
                        <p className="text-2xl font-semibold">{inspecting.seo.score}<span className="text-base text-default-400">/100</span></p>
                      </div>
                      <div className="flex flex-col items-end gap-1">
                        <Chip color={httpStatusColor(inspecting.http_status)} size="sm" variant="flat">
                          HTTP {inspecting.http_status}
                        </Chip>
                        <span className="text-xs text-default-400">{formatAge(inspecting.age_s)} old</span>
                      </div>
                    </div>
                    {(inspecting.seo.issues.length > 0 || inspecting.seo.tips.length > 0) && (
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {inspecting.seo.issues.length > 0 && (
                          <div>
                            <p className="font-semibold mb-1 text-danger flex items-center gap-1">
                              <AlertOctagon size={14} />Must fix ({inspecting.seo.issues.length})
                            </p>
                            <ul className="text-xs space-y-0.5 list-disc list-inside">
                              {inspecting.seo.issues.map((s, i) => <li key={i}>{s}</li>)}
                            </ul>
                          </div>
                        )}
                        {inspecting.seo.tips.length > 0 && (
                          <div>
                            <p className="font-semibold mb-1 text-warning flex items-center gap-1">
                              <Activity size={14} />Tips ({inspecting.seo.tips.length})
                            </p>
                            <ul className="text-xs space-y-0.5 list-disc list-inside">
                              {inspecting.seo.tips.map((s, i) => <li key={i}>{s}</li>)}
                            </ul>
                          </div>
                        )}
                      </div>
                    )}
                    <Divider />
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
  const [bulkLoading, setBulkLoading] = useState(false);

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

  /**
   * Bulk-enqueue recache for every tenant that has missing or stale routes.
   * Targets only the affected routes (not a full force-rerender) so the cost
   * is bounded.
   */
  const refreshAllStale = async () => {
    const needsWork = rows.filter(
      (r) => r.missing_routes.length > 0 || r.stale_routes.length > 0 || r.asset_invalid_routes.length > 0,
    );
    if (needsWork.length === 0) {
      toast.success('No stale tenants — coverage is healthy');
      return;
    }
    if (!confirm(`Queue recache for ${needsWork.length} tenants?`)) return;
    setBulkLoading(true);
    let queued = 0;
    try {
      for (const r of needsWork) {
        const allRoutes = Array.from(new Set([
          ...r.missing_routes,
          ...r.stale_routes,
          ...r.asset_invalid_routes,
        ]));
        if (allRoutes.length === 0) continue;
        await adminPrerender.enqueueJob({
          tenant_slug: r.slug,
          routes: allRoutes.join(','),
        });
        queued++;
      }
      toast.success(`Queued recache for ${queued} tenants`);
      load();
    } catch {
      toast.error('Bulk enqueue failed (some tenants may have queued)');
    } finally {
      setBulkLoading(false);
    }
  };

  if (loading) return <div className="flex justify-center py-8"><Spinner /></div>;

  const totalNeedingWork = rows.filter(
    (r) => r.missing_routes.length > 0 || r.stale_routes.length > 0 || r.asset_invalid_routes.length > 0,
  ).length;

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-sm text-default-500">
          {totalNeedingWork === 0
            ? <>All {rows.length} tenants healthy.</>
            : <>{totalNeedingWork} of {rows.length} tenants have missing, stale, or asset-broken routes.</>}
        </p>
        <Button
          color="primary"
          variant="flat"
          startContent={<Zap size={14} />}
          onPress={refreshAllStale}
          isLoading={bulkLoading}
          isDisabled={!isSuperAdmin || totalNeedingWork === 0}
        >
          Refresh all stale ({totalNeedingWork})
        </Button>
      </div>
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
    </div>
  );
}

// ─── Analytics ─────────────────────────────────────────────────────────────

/**
 * Bot-only access analytics. Sourced from the nginx JSONL log written for
 * every search engine / social / AI crawler hit. Shows the breakdown by
 * crawler, host, status code, and which "claimed Googlebot" hits failed
 * IP-range verification — the spoofing signal.
 */
function AnalyticsTab() {
  const toast = useToast();
  const [data, setData] = useState<PrerenderAnalytics | null>(null);
  const [loading, setLoading] = useState(true);
  const [windowDays, setWindowDays] = useState<string>('7');

  const load = useCallback(() => {
    setLoading(true);
    const sinceIso = new Date(Date.now() - parseInt(windowDays, 10) * 86400_000).toISOString();
    adminPrerender.getAnalytics({ since: sinceIso, limit: 300 })
      .then((res) => { if (res.data) setData(res.data); })
      .catch(() => toast.error('Failed to load analytics'))
      .finally(() => setLoading(false));
  }, [toast, windowDays]);

  useEffect(() => { load(); }, [load]);

  if (loading && !data) return <div className="flex justify-center py-8"><Spinner /></div>;
  if (!data) return <p className="text-default-500">No analytics available. Bot access log may be empty.</p>;

  const verifiedPct = data.total_hits > 0 ? Math.round((data.verified_hits / data.total_hits) * 100) : 0;
  const totalSpoofed = Object.values(data.spoofed_by_crawler).reduce((a, b) => a + b, 0);

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <Select
          label="Window"
          variant="bordered"
          selectedKeys={[windowDays]}
          onSelectionChange={(s) => setWindowDays(Array.from(s)[0] as string)}
          className="max-w-[150px]"
        >
          <SelectItem key="1">Last 24h</SelectItem>
          <SelectItem key="7">Last 7 days</SelectItem>
          <SelectItem key="30">Last 30 days</SelectItem>
        </Select>
        <Button variant="flat" onPress={load} startContent={<RefreshCw size={14} />} className="self-end">Reload</Button>
        <span className="text-sm text-default-500 ml-auto self-end">
          Log: {formatBytes(data.log_size_bytes)} · since {formatTs(data.window_started_at)}
        </span>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <KpiCard label="Total bot hits" value={data.total_hits} />
        <KpiCard
          label="IP-verified"
          value={`${verifiedPct}%`}
          hint={`${data.verified_hits} of ${data.total_hits}`}
          tone={verifiedPct >= 80 ? 'default' : 'warning'}
        />
        <KpiCard
          label="Spoofed (suspicious)"
          value={totalSpoofed}
          hint="claimed major bot, failed IP range"
          tone={totalSpoofed > 0 ? 'danger' : 'default'}
        />
        <KpiCard
          label="Unique URIs"
          value={data.top_uris.length}
          hint="from top-50 cap"
        />
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Card shadow="sm">
          <CardHeader><h3 className="font-semibold">Hits by crawler</h3></CardHeader>
          <CardBody className="text-xs space-y-1">
            {Object.entries(data.hits_by_crawler).map(([k, n]) => (
              <div key={k} className="flex items-center gap-2">
                <Chip size="sm" variant="flat">{k}</Chip>
                <span className="font-mono ml-auto">{n}</span>
                {data.spoofed_by_crawler[k] && (
                  <Chip size="sm" variant="flat" color="danger">{data.spoofed_by_crawler[k]} spoofed</Chip>
                )}
              </div>
            ))}
            {Object.keys(data.hits_by_crawler).length === 0 && <p className="text-default-400">No data</p>}
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="font-semibold">Hits by HTTP status</h3></CardHeader>
          <CardBody className="text-xs space-y-1">
            {Object.entries(data.hits_by_status).map(([k, n]) => (
              <div key={k} className="flex items-center gap-2">
                <Chip size="sm" variant="flat" color={httpStatusColor(Number(k))}>{k}</Chip>
                <span className="font-mono ml-auto">{n}</span>
              </div>
            ))}
            {Object.keys(data.hits_by_status).length === 0 && <p className="text-default-400">No data</p>}
          </CardBody>
        </Card>
      </div>

      <Card shadow="sm">
        <CardHeader><h3 className="font-semibold">Top URIs (top 50 by hit count)</h3></CardHeader>
        <CardBody className="p-0">
          <Table aria-label="Top URIs" removeWrapper isStriped>
            <TableHeader>
              <TableColumn>URL</TableColumn>
              <TableColumn>HITS</TableColumn>
            </TableHeader>
            <TableBody emptyContent="No bot traffic yet">
              {data.top_uris.map((u, i) => (
                <TableRow key={i}>
                  <TableCell className="text-xs font-mono">{u.url}</TableCell>
                  <TableCell className="text-xs">{u.hits}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>

      <Card shadow="sm">
        <CardHeader><h3 className="font-semibold">Recent activity (newest first, last {data.recent.length})</h3></CardHeader>
        <CardBody className="p-0">
          <Table aria-label="Recent bot hits" removeWrapper isStriped>
            <TableHeader>
              <TableColumn>TIME</TableColumn>
              <TableColumn>CRAWLER</TableColumn>
              <TableColumn>HOST</TableColumn>
              <TableColumn>URI</TableColumn>
              <TableColumn>STATUS</TableColumn>
              <TableColumn>IP</TableColumn>
            </TableHeader>
            <TableBody emptyContent="No recent hits">
              {data.recent.map((r, i) => (
                <TableRow key={i}>
                  <TableCell className="text-xs">{formatTs(r.ts ?? null)}</TableCell>
                  <TableCell className="text-xs">
                    <Chip size="sm" variant="flat">{r.crawler}</Chip>
                  </TableCell>
                  <TableCell className="text-xs">{r.host}</TableCell>
                  <TableCell className="text-xs font-mono">{r.uri}</TableCell>
                  <TableCell>
                    <Chip size="sm" variant="flat" color={httpStatusColor(r.status)}>{r.status}</Chip>
                  </TableCell>
                  <TableCell className="text-xs font-mono">{r.ip}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>
    </div>
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
