// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter Activity
 * Activity log page showing open/click events for a specific newsletter.
 */

import { useState, useCallback, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card, CardBody, Button, Chip, Tabs, Tab, Skeleton, Pagination,
  Table, TableHeader, TableColumn, TableBody, TableRow, TableCell,
} from '@heroui/react';
import { ArrowLeft, Eye, MousePointer, Activity, Inbox } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminNewsletters } from '../../api/adminApi';
import { PageHeader } from '../../components';

// ─── Types ──────────────────────────────────────────────────────────────────

type ActivityType = 'all' | 'open' | 'click';

interface ActivityEvent {
  id: number;
  email: string;
  action_type: 'open' | 'click';
  url: string | null;
  action_at: string;
  user_agent: string | null;
  ip_address: string | null;
}

interface ActivityResponse {
  data: ActivityEvent[];
  meta?: {
    total?: number;
    page?: number;
    per_page?: number;
    total_pages?: number;
  };
}

// ─── Component ──────────────────────────────────────────────────────────────

export function NewsletterActivity() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();
  usePageTitle('Admin - Newsletter Activity');

  const [events, setEvents] = useState<ActivityEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<ActivityType>('all');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const perPage = 25;

  const loadActivity = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const params: { page: number; per_page: number; type?: string } = {
        page,
        per_page: perPage,
      };
      if (activeTab !== 'all') {
        params.type = activeTab;
      }
      const res = await adminNewsletters.getActivity(Number(id), params);
      if (res.success && res.data) {
        const raw = res.data as unknown;
        // respondWithPaginatedCollection returns { data: [...], meta: { total_pages } }
        let payload: ActivityResponse;
        if (raw && typeof raw === 'object' && 'data' in raw) {
          payload = raw as ActivityResponse;
        } else if (Array.isArray(raw)) {
          payload = { data: raw as ActivityEvent[] };
        } else {
          payload = { data: [] };
        }
        setEvents(payload.data ?? []);
        setTotalPages(payload.meta?.total_pages ?? 1);
      } else {
        setEvents([]);
        setTotalPages(1);
      }
    } catch {
      toast.error('Failed to load activity log');
      setEvents([]);
      setTotalPages(1);
    }
    setLoading(false);
  }, [id, page, activeTab, toast]);

  useEffect(() => {
    loadActivity();
  }, [loadActivity]);

  // Reset page when filter changes
  useEffect(() => {
    setPage(1);
  }, [activeTab]);

  const truncateUserAgent = (ua: string | null, maxLen = 60): string => {
    if (!ua) return '--';
    return ua.length > maxLen ? ua.substring(0, maxLen) + '...' : ua;
  };

  const formatTime = (dateStr: string): string => {
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const backPath = tenantPath(`/admin/newsletters/${id}/stats`);

  // ── Loading State ──
  if (loading && events.length === 0) {
    return (
      <div>
        <PageHeader
          title="Newsletter Activity"
          actions={
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(backPath)}
            >
              Back to Stats
            </Button>
          }
        />
        <div className="space-y-4">
          <Skeleton className="h-12 w-full rounded-xl" />
          <Skeleton className="h-64 w-full rounded-xl" />
        </div>
      </div>
    );
  }

  return (
    <div>
      {/* ── Header ── */}
      <PageHeader
        title="Newsletter Activity"
        description="Open and click events for this newsletter"
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(backPath)}
          >
            Back to Stats
          </Button>
        }
      />

      {/* ── Filter Tabs + Table ── */}
      <Card shadow="sm">
        <CardBody className="space-y-4 p-5">
          {/* Filter Tabs */}
          <Tabs
            selectedKey={activeTab}
            onSelectionChange={(key) => setActiveTab(key as ActivityType)}
            aria-label="Activity type filter"
            color="primary"
            variant="underlined"
          >
            <Tab
              key="all"
              title={
                <div className="flex items-center gap-2">
                  <Activity size={16} />
                  <span>All</span>
                </div>
              }
            />
            <Tab
              key="open"
              title={
                <div className="flex items-center gap-2">
                  <Eye size={16} />
                  <span>Opens</span>
                </div>
              }
            />
            <Tab
              key="click"
              title={
                <div className="flex items-center gap-2">
                  <MousePointer size={16} />
                  <span>Clicks</span>
                </div>
              }
            />
          </Tabs>

          {/* Table */}
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
              items={events}
              isLoading={loading}
              loadingContent={<Skeleton className="h-48 w-full rounded-xl" />}
              emptyContent={
                <div className="flex flex-col items-center gap-3 py-12 text-center">
                  <Inbox size={48} className="text-default-300" />
                  <p className="text-lg font-semibold text-default-500">No activity found</p>
                  <p className="text-sm text-default-400">
                    {activeTab === 'all'
                      ? 'No open or click events recorded for this newsletter yet.'
                      : `No ${activeTab} events recorded for this newsletter yet.`}
                  </p>
                </div>
              }
            >
              {(event) => (
                <TableRow key={event.id}>
                  <TableCell>
                    <span className="font-mono text-sm">{event.email}</span>
                  </TableCell>
                  <TableCell>
                    <Chip
                      size="sm"
                      color={event.action_type === 'open' ? 'primary' : 'success'}
                      variant="flat"
                      startContent={
                        event.action_type === 'open'
                          ? <Eye size={12} />
                          : <MousePointer size={12} />
                      }
                    >
                      {event.action_type}
                    </Chip>
                  </TableCell>
                  <TableCell>
                    {event.url ? (
                      <a
                        href={event.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="break-all text-sm text-primary hover:underline"
                      >
                        {event.url.length > 60 ? event.url.substring(0, 60) + '...' : event.url}
                      </a>
                    ) : (
                      <span className="text-sm text-default-400">--</span>
                    )}
                  </TableCell>
                  <TableCell>
                    <span className="text-sm text-default-600">
                      {formatTime(event.action_at)}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span
                      className="text-xs text-default-500"
                      title={event.user_agent ?? undefined}
                    >
                      {truncateUserAgent(event.user_agent)}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="font-mono text-xs text-default-500">
                      {event.ip_address ?? '--'}
                    </span>
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>

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

export default NewsletterActivity;
