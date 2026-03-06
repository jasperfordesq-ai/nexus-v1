// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter Activity & Engagement
 * Tabbed page showing:
 *  - Activity Log (all opens/clicks chronologically)
 *  - Who Opened (per-subscriber openers list)
 *  - Who Clicked (per-subscriber clickers list)
 *  - Non-Openers (recipients who never opened)
 *  - Opened, No Click (opened but didn't click)
 */

import { useState, useCallback, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card, CardBody, Button, Chip, Tabs, Tab, Skeleton, Pagination,
  Table, TableHeader, TableColumn, TableBody, TableRow, TableCell,
} from '@heroui/react';
import {
  ArrowLeft, Eye, MousePointer, Activity, Inbox, Users,
  UserX, UserCheck, Download,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminNewsletters } from '../../api/adminApi';
import { PageHeader } from '../../components';

// ─── Types ──────────────────────────────────────────────────────────────────

type ViewTab = 'activity' | 'openers' | 'clickers' | 'non-openers' | 'opened-no-click';

interface ActivityEvent {
  id: number;
  email: string;
  action_type: 'open' | 'click';
  url: string | null;
  action_at: string;
  user_agent: string | null;
  ip_address: string | null;
}

interface OpenerRow {
  email: string;
  first_opened: string;
  open_count: number;
}

interface ClickerRow {
  email: string;
  first_clicked: string;
  click_count: number;
  unique_links: number;
}

interface NonOpenerRow {
  email: string;
  name: string | null;
  sent_at: string;
}

interface OpenedNoClickRow {
  email: string;
  name: string | null;
  first_opened: string;
  open_count: number;
}

interface PaginatedResponse<T> {
  data: T[];
  meta?: {
    total?: number;
    page?: number;
    per_page?: number;
    total_pages?: number;
  };
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function formatTime(dateStr: string): string {
  if (!dateStr) return '--';
  return new Date(dateStr).toLocaleDateString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

function truncateUA(ua: string | null, maxLen = 60): string {
  if (!ua) return '--';
  return ua.length > maxLen ? ua.substring(0, maxLen) + '...' : ua;
}

function parsePaginated<T>(raw: unknown): PaginatedResponse<T> {
  if (raw && typeof raw === 'object' && 'data' in raw) {
    return raw as PaginatedResponse<T>;
  }
  if (Array.isArray(raw)) {
    return { data: raw as T[] };
  }
  return { data: [] };
}

function escapeCsvField(field: string): string {
  let s = (field ?? '').replace(/"/g, '""');
  // Prefix formula-triggering characters to prevent CSV injection
  if (/^[=+\-@\t\r]/.test(s)) s = "'" + s;
  return `"${s}"`;
}

function downloadCsv(rows: string[][], filename: string) {
  const csv = rows.map((r) => r.map(escapeCsvField).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

// ─── Component ──────────────────────────────────────────────────────────────

export function NewsletterActivity() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();
  usePageTitle('Admin - Newsletter Engagement');

  const [activeTab, setActiveTab] = useState<ViewTab>('activity');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const perPage = 50;

  // Data states per tab
  const [activityFilter, setActivityFilter] = useState<'all' | 'open' | 'click'>('all');
  const [activityEvents, setActivityEvents] = useState<ActivityEvent[]>([]);
  const [openers, setOpeners] = useState<OpenerRow[]>([]);
  const [clickers, setClickers] = useState<ClickerRow[]>([]);
  const [nonOpeners, setNonOpeners] = useState<NonOpenerRow[]>([]);
  const [openedNoClick, setOpenedNoClick] = useState<OpenedNoClickRow[]>([]);

  const loadData = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const nid = Number(id);
      if (activeTab === 'activity') {
        const params: { page: number; per_page: number; type?: string } = { page, per_page: perPage };
        if (activityFilter !== 'all') params.type = activityFilter;
        const res = await adminNewsletters.getActivity(nid, params);
        if (res.success && res.data) {
          const payload = parsePaginated<ActivityEvent>(res.data);
          setActivityEvents(payload.data);
          setTotalPages(payload.meta?.total_pages ?? 1);
          setTotalCount(payload.meta?.total ?? payload.data.length);
        }
      } else if (activeTab === 'openers') {
        const res = await adminNewsletters.getOpeners(nid, { page, per_page: perPage });
        if (res.success && res.data) {
          const payload = parsePaginated<OpenerRow>(res.data);
          setOpeners(payload.data);
          setTotalPages(payload.meta?.total_pages ?? 1);
          setTotalCount(payload.meta?.total ?? payload.data.length);
        }
      } else if (activeTab === 'clickers') {
        const res = await adminNewsletters.getClickers(nid, { page, per_page: perPage });
        if (res.success && res.data) {
          const payload = parsePaginated<ClickerRow>(res.data);
          setClickers(payload.data);
          setTotalPages(payload.meta?.total_pages ?? 1);
          setTotalCount(payload.meta?.total ?? payload.data.length);
        }
      } else if (activeTab === 'non-openers') {
        const res = await adminNewsletters.getNonOpeners(nid, { page, per_page: perPage });
        if (res.success && res.data) {
          const payload = parsePaginated<NonOpenerRow>(res.data);
          setNonOpeners(payload.data);
          setTotalPages(payload.meta?.total_pages ?? 1);
          setTotalCount(payload.meta?.total ?? payload.data.length);
        }
      } else if (activeTab === 'opened-no-click') {
        const res = await adminNewsletters.getOpenersNoClick(nid, { page, per_page: perPage });
        if (res.success && res.data) {
          const payload = parsePaginated<OpenedNoClickRow>(res.data);
          setOpenedNoClick(payload.data);
          setTotalPages(payload.meta?.total_pages ?? 1);
          setTotalCount(payload.meta?.total ?? payload.data.length);
        }
      }
    } catch {
      toast.error('Failed to load data');
    }
    setLoading(false);
  }, [id, activeTab, page, activityFilter, toast]);

  useEffect(() => { loadData(); }, [loadData]);

  // Reset page on tab/filter change
  useEffect(() => { setPage(1); }, [activeTab, activityFilter]);

  const handleExport = () => {
    if (activeTab === 'openers') {
      downloadCsv(
        [['Email', 'First Opened', 'Open Count'], ...openers.map((r) => [r.email, r.first_opened, String(r.open_count)])],
        `newsletter-${id}-openers.csv`,
      );
    } else if (activeTab === 'clickers') {
      downloadCsv(
        [['Email', 'First Clicked', 'Click Count', 'Unique Links'], ...clickers.map((r) => [r.email, r.first_clicked, String(r.click_count), String(r.unique_links)])],
        `newsletter-${id}-clickers.csv`,
      );
    } else if (activeTab === 'non-openers') {
      downloadCsv(
        [['Email', 'Name', 'Sent At'], ...nonOpeners.map((r) => [r.email, r.name ?? '', r.sent_at])],
        `newsletter-${id}-non-openers.csv`,
      );
    } else if (activeTab === 'opened-no-click') {
      downloadCsv(
        [['Email', 'Name', 'First Opened', 'Open Count'], ...openedNoClick.map((r) => [r.email, r.name ?? '', r.first_opened, String(r.open_count)])],
        `newsletter-${id}-opened-no-click.csv`,
      );
    }
  };

  const backPath = tenantPath(`/admin/newsletters/${id}/stats`);
  const showExport = activeTab !== 'activity';
  const currentData = activeTab === 'activity' ? activityEvents
    : activeTab === 'openers' ? openers
    : activeTab === 'clickers' ? clickers
    : activeTab === 'non-openers' ? nonOpeners
    : openedNoClick;

  return (
    <div>
      <PageHeader
        title="Newsletter Engagement"
        description={totalCount > 0 ? `${totalCount.toLocaleString()} total records` : undefined}
        actions={
          <div className="flex gap-2">
            {showExport && currentData.length > 0 && (
              <Button
                variant="flat"
                startContent={<Download size={16} />}
                onPress={handleExport}
              >
                Export CSV
              </Button>
            )}
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(backPath)}
            >
              Back to Stats
            </Button>
          </div>
        }
      />

      <Card shadow="sm">
        <CardBody className="space-y-4 p-5">
          {/* Main Tabs */}
          <Tabs
            selectedKey={activeTab}
            onSelectionChange={(key) => setActiveTab(key as ViewTab)}
            aria-label="Engagement view"
            color="primary"
            variant="underlined"
            classNames={{ tabList: 'flex-wrap' }}
          >
            <Tab
              key="activity"
              title={
                <div className="flex items-center gap-2">
                  <Activity size={16} />
                  <span>Activity Log</span>
                </div>
              }
            />
            <Tab
              key="openers"
              title={
                <div className="flex items-center gap-2">
                  <Eye size={16} />
                  <span>Who Opened</span>
                </div>
              }
            />
            <Tab
              key="clickers"
              title={
                <div className="flex items-center gap-2">
                  <MousePointer size={16} />
                  <span>Who Clicked</span>
                </div>
              }
            />
            <Tab
              key="non-openers"
              title={
                <div className="flex items-center gap-2">
                  <UserX size={16} />
                  <span>Non-Openers</span>
                </div>
              }
            />
            <Tab
              key="opened-no-click"
              title={
                <div className="flex items-center gap-2">
                  <UserCheck size={16} />
                  <span>Opened, No Click</span>
                </div>
              }
            />
          </Tabs>

          {/* Activity sub-filter */}
          {activeTab === 'activity' && (
            <div className="flex gap-2">
              {(['all', 'open', 'click'] as const).map((f) => (
                <Chip
                  key={f}
                  size="sm"
                  variant={activityFilter === f ? 'solid' : 'flat'}
                  color={f === 'open' ? 'primary' : f === 'click' ? 'success' : 'default'}
                  className="cursor-pointer"
                  onClick={() => setActivityFilter(f)}
                >
                  {f === 'all' ? 'All Events' : f === 'open' ? 'Opens Only' : 'Clicks Only'}
                </Chip>
              ))}
            </div>
          )}

          {/* Loading skeleton */}
          {loading && currentData.length === 0 && (
            <Skeleton className="h-64 w-full rounded-xl" />
          )}

          {/* ── Activity Log Table ── */}
          {activeTab === 'activity' && !loading && (
            <Table
              aria-label="Newsletter activity log"
              isStriped
              classNames={{ th: 'text-default-500 text-xs uppercase' }}
            >
              <TableHeader>
                <TableColumn>EMAIL</TableColumn>
                <TableColumn>ACTION</TableColumn>
                <TableColumn>URL</TableColumn>
                <TableColumn>TIME</TableColumn>
                <TableColumn>USER AGENT</TableColumn>
                <TableColumn>IP</TableColumn>
              </TableHeader>
              <TableBody
                items={activityEvents}
                emptyContent={
                  <EmptyState message={`No ${activityFilter === 'all' ? '' : activityFilter + ' '}events recorded yet.`} />
                }
              >
                {(event) => (
                  <TableRow key={event.id}>
                    <TableCell><span className="font-mono text-sm">{event.email}</span></TableCell>
                    <TableCell>
                      <Chip
                        size="sm"
                        color={event.action_type === 'open' ? 'primary' : 'success'}
                        variant="flat"
                        startContent={event.action_type === 'open' ? <Eye size={12} /> : <MousePointer size={12} />}
                      >
                        {event.action_type}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      {event.url ? (
                        <a href={event.url} target="_blank" rel="noopener noreferrer" className="break-all text-sm text-primary hover:underline">
                          {event.url.length > 60 ? event.url.substring(0, 60) + '...' : event.url}
                        </a>
                      ) : <span className="text-sm text-default-400">--</span>}
                    </TableCell>
                    <TableCell><span className="text-sm text-default-600">{formatTime(event.action_at)}</span></TableCell>
                    <TableCell><span className="text-xs text-default-500" title={event.user_agent ?? undefined}>{truncateUA(event.user_agent)}</span></TableCell>
                    <TableCell><span className="font-mono text-xs text-default-500">{event.ip_address ?? '--'}</span></TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          )}

          {/* ── Openers Table ── */}
          {activeTab === 'openers' && !loading && (
            <Table
              aria-label="Newsletter openers"
              isStriped
              classNames={{ th: 'text-default-500 text-xs uppercase' }}
            >
              <TableHeader>
                <TableColumn>EMAIL</TableColumn>
                <TableColumn align="center">OPEN COUNT</TableColumn>
                <TableColumn>FIRST OPENED</TableColumn>
              </TableHeader>
              <TableBody
                items={openers}
                emptyContent={<EmptyState message="No one has opened this newsletter yet." />}
              >
                {(row) => (
                  <TableRow key={row.email}>
                    <TableCell><span className="font-mono text-sm">{row.email}</span></TableCell>
                    <TableCell>
                      <div className="text-center">
                        <Chip size="sm" variant="flat" color="primary">{row.open_count}</Chip>
                      </div>
                    </TableCell>
                    <TableCell><span className="text-sm text-default-600">{formatTime(row.first_opened)}</span></TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          )}

          {/* ── Clickers Table ── */}
          {activeTab === 'clickers' && !loading && (
            <Table
              aria-label="Newsletter clickers"
              isStriped
              classNames={{ th: 'text-default-500 text-xs uppercase' }}
            >
              <TableHeader>
                <TableColumn>EMAIL</TableColumn>
                <TableColumn align="center">CLICK COUNT</TableColumn>
                <TableColumn align="center">UNIQUE LINKS</TableColumn>
                <TableColumn>FIRST CLICKED</TableColumn>
              </TableHeader>
              <TableBody
                items={clickers}
                emptyContent={<EmptyState message="No one has clicked a link in this newsletter yet." />}
              >
                {(row) => (
                  <TableRow key={row.email}>
                    <TableCell><span className="font-mono text-sm">{row.email}</span></TableCell>
                    <TableCell>
                      <div className="text-center">
                        <Chip size="sm" variant="flat" color="success">{row.click_count}</Chip>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="text-center">
                        <span className="text-sm text-default-600">{row.unique_links}</span>
                      </div>
                    </TableCell>
                    <TableCell><span className="text-sm text-default-600">{formatTime(row.first_clicked)}</span></TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          )}

          {/* ── Non-Openers Table ── */}
          {activeTab === 'non-openers' && !loading && (
            <Table
              aria-label="Newsletter non-openers"
              isStriped
              classNames={{ th: 'text-default-500 text-xs uppercase' }}
            >
              <TableHeader>
                <TableColumn>EMAIL</TableColumn>
                <TableColumn>NAME</TableColumn>
                <TableColumn>SENT AT</TableColumn>
              </TableHeader>
              <TableBody
                items={nonOpeners}
                emptyContent={<EmptyState message="Everyone who was sent this newsletter has opened it!" icon={<Users size={48} className="text-success-300" />} />}
              >
                {(row) => (
                  <TableRow key={row.email}>
                    <TableCell><span className="font-mono text-sm">{row.email}</span></TableCell>
                    <TableCell><span className="text-sm text-default-600">{row.name || '--'}</span></TableCell>
                    <TableCell><span className="text-sm text-default-600">{formatTime(row.sent_at)}</span></TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          )}

          {/* ── Opened No Click Table ── */}
          {activeTab === 'opened-no-click' && !loading && (
            <Table
              aria-label="Opened but didn't click"
              isStriped
              classNames={{ th: 'text-default-500 text-xs uppercase' }}
            >
              <TableHeader>
                <TableColumn>EMAIL</TableColumn>
                <TableColumn>NAME</TableColumn>
                <TableColumn align="center">OPEN COUNT</TableColumn>
                <TableColumn>FIRST OPENED</TableColumn>
              </TableHeader>
              <TableBody
                items={openedNoClick}
                emptyContent={<EmptyState message="Everyone who opened also clicked a link!" icon={<MousePointer size={48} className="text-success-300" />} />}
              >
                {(row) => (
                  <TableRow key={row.email}>
                    <TableCell><span className="font-mono text-sm">{row.email}</span></TableCell>
                    <TableCell><span className="text-sm text-default-600">{row.name || '--'}</span></TableCell>
                    <TableCell>
                      <div className="text-center">
                        <Chip size="sm" variant="flat" color="primary">{row.open_count}</Chip>
                      </div>
                    </TableCell>
                    <TableCell><span className="text-sm text-default-600">{formatTime(row.first_opened)}</span></TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="flex justify-center pt-2">
              <Pagination
                total={totalPages}
                page={page}
                onChange={setPage}
                showControls
                color="primary"
              />
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

// ─── Sub-Components ─────────────────────────────────────────────────────────

function EmptyState({ message, icon }: { message: string; icon?: React.ReactNode }) {
  return (
    <div className="flex flex-col items-center gap-3 py-12 text-center">
      {icon || <Inbox size={48} className="text-default-300" />}
      <p className="text-lg font-semibold text-default-500">No data</p>
      <p className="text-sm text-default-400">{message}</p>
    </div>
  );
}

export default NewsletterActivity;
