// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter Stats
 * Detailed per-campaign statistics page for a single newsletter.
 * Shows delivery funnel, engagement metrics, device breakdown,
 * A/B test results, timeline, top links, recent activity, and quick actions.
 */

import { useState, useCallback, useEffect, useMemo, type CSSProperties } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card, CardBody, CardHeader, Button, Chip, Progress, Skeleton,
  Table, TableHeader, TableColumn, TableBody, TableRow, TableCell,
  Divider,
} from '@heroui/react';
import {
  ArrowLeft, CheckCircle, Eye, MousePointer, BarChart3,
  Trophy, Send, Mail, ExternalLink, Clock, Monitor, Smartphone,
  Tablet, HelpCircle, Copy, FileText, TrendingUp,
} from 'lucide-react';
import {
  ResponsiveContainer, LineChart, Line, XAxis, YAxis, CartesianGrid,
  Tooltip, Legend, PieChart, Pie, Cell,
} from 'recharts';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminNewsletters } from '../../api/adminApi';
import { PageHeader, StatCard } from '../../components';
import { NewsletterResend } from './NewsletterResend';

// ─── Types ──────────────────────────────────────────────────────────────────

interface NewsletterInfo {
  id: number;
  name: string;
  subject: string;
  subject_b: string | null;
  status: string;
  ab_test_enabled: boolean;
  ab_winner: string | null;
  ab_winner_metric: string;
  created_by: number;
  author_name: string | null;
  sent_at: string | null;
  created_at: string | null;
}

interface DeliveryStats {
  total_sent: number;
  delivered: number;
  failed: number;
  bounced: number;
  pending: number;
}

interface EngagementStats {
  unique_opens: number;
  total_opens: number;
  unique_clicks: number;
  total_clicks: number;
  open_rate: number;
  click_rate: number;
  click_to_open_rate: number;
  success_rate: number;
}

interface AbTestData {
  subject_a: string;
  subject_b: string;
  subject_a_opens: number;
  subject_a_clicks: number;
  subject_b_opens: number;
  subject_b_clicks: number;
  subject_a_sent: number;
  subject_b_sent: number;
  subject_a_open_rate: number;
  subject_b_open_rate: number;
  subject_a_click_rate: number;
  subject_b_click_rate: number;
  split_percentage: number;
  winner_metric: string;
  winner: string | null;
  winning_margin: number;
}

interface TimelinePoint {
  hour: number;
  opens: number;
  clicks: number;
}

interface TopLink {
  url: string;
  clicks: number;
  unique_clicks: number;
}

interface DeviceStats {
  desktop: number;
  mobile: number;
  tablet: number;
  unknown: number;
}

interface RecentActivityItem {
  action_type: 'open' | 'click';
  email: string;
  action_at: string;
  url: string | null;
}

interface PeakEngagement {
  max_opens_per_hour: number;
  peak_hour: number | null;
}

interface StatsData {
  newsletter: NewsletterInfo;
  delivery: DeliveryStats;
  engagement: EngagementStats;
  ab_test: AbTestData | null;
  timeline: TimelinePoint[];
  top_links: TopLink[];
  device_stats: DeviceStats;
  recent_activity: RecentActivityItem[];
  peak_engagement: PeakEngagement;
}

// ─── Constants ──────────────────────────────────────────────────────────────

const DEVICE_COLORS: Record<string, string> = {
  desktop: 'hsl(var(--heroui-primary))',
  mobile: 'hsl(var(--heroui-success))',
  tablet: 'hsl(var(--heroui-warning))',
  unknown: 'hsl(var(--heroui-default-400))',
};

const DEVICE_ICONS: Record<string, typeof Monitor> = {
  desktop: Monitor,
  mobile: Smartphone,
  tablet: Tablet,
  unknown: HelpCircle,
};

// ─── Component ──────────────────────────────────────────────────────────────

export function NewsletterStats() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();
  usePageTitle('Admin - Newsletter Stats');

  const [data, setData] = useState<StatsData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectingWinner, setSelectingWinner] = useState(false);
  const [resendOpen, setResendOpen] = useState(false);
  const [emailClients, setEmailClients] = useState<Array<{ client: string; count: number }>>([]);

  const loadStats = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const nid = Number(id);
      const [statsRes, clientsRes] = await Promise.all([
        adminNewsletters.getStats(nid),
        adminNewsletters.getEmailClients(nid),
      ]);
      if (statsRes.success && statsRes.data) {
        setData(statsRes.data as unknown as StatsData);
      } else {
        setError('Newsletter not found');
      }
      if (clientsRes.success && clientsRes.data) {
        const raw = clientsRes.data as unknown as { email_clients?: Array<{ client: string; count: number }> };
        setEmailClients(raw?.email_clients ?? []);
      }
    } catch {
      setError('Failed to load newsletter stats');
    }
    setLoading(false);
  }, [id]);

  useEffect(() => { loadStats(); }, [loadStats]);

  const handleSelectWinner = async (winner: 'a' | 'b') => {
    if (!id) return;
    setSelectingWinner(true);
    try {
      const res = await adminNewsletters.selectAbWinner(Number(id), winner);
      if (res.success) {
        toast.success(`Subject ${winner.toUpperCase()} selected as winner`);
        loadStats();
      } else {
        toast.error('Failed to select winner');
      }
    } catch {
      toast.error('Failed to select winner');
    }
    setSelectingWinner(false);
  };

  // Device chart data
  const deviceChartData = useMemo(() => {
    if (!data?.device_stats) return [];
    return Object.entries(data.device_stats)
      .filter(([, count]) => count > 0)
      .map(([device, count]) => ({
        name: device.charAt(0).toUpperCase() + device.slice(1),
        value: count,
        color: DEVICE_COLORS[device] || DEVICE_COLORS.unknown,
      }));
  }, [data?.device_stats]);

  const deviceTotal = useMemo(() => {
    if (!data?.device_stats) return 0;
    return Object.values(data.device_stats).reduce((sum, c) => sum + c, 0);
  }, [data?.device_stats]);

  // ── Loading State ──
  if (loading) {
    return (
      <div>
        <PageHeader
          title="Newsletter Stats"
          actions={
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/newsletters'))}
            >
              Back
            </Button>
          }
        />
        <div className="space-y-4">
          <Skeleton className="h-24 w-full rounded-xl" />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {[1, 2, 3, 4].map((i) => (
              <Skeleton key={i} className="h-24 rounded-xl" />
            ))}
          </div>
          <Skeleton className="h-48 w-full rounded-xl" />
          <Skeleton className="h-64 w-full rounded-xl" />
        </div>
      </div>
    );
  }

  // ── Error State ──
  if (error || !data) {
    return (
      <div>
        <PageHeader
          title="Newsletter Stats"
          actions={
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/newsletters'))}
            >
              Back
            </Button>
          }
        />
        <Card>
          <CardBody className="flex flex-col items-center gap-3 py-12 text-center">
            <Mail size={48} className="text-default-300" />
            <p className="text-lg font-semibold text-default-600">{error || 'Newsletter not found'}</p>
            <Button
              color="primary"
              variant="flat"
              onPress={() => navigate(tenantPath('/admin/newsletters'))}
            >
              Back to Newsletters
            </Button>
          </CardBody>
        </Card>
      </div>
    );
  }

  const { newsletter, delivery, engagement, ab_test, timeline, top_links, device_stats, recent_activity, peak_engagement } = data;
  const nonOpenerCount = delivery.delivered - engagement.unique_opens;

  return (
    <div>
      {/* ── Header ── */}
      <PageHeader
        title={newsletter.subject || newsletter.name}
        description="Campaign performance metrics"
        actions={
          <div className="flex flex-wrap gap-2">
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/newsletters'))}
            >
              Back
            </Button>
            {newsletter.status === 'sent' && (
              <Button
                variant="flat"
                startContent={<BarChart3 size={16} />}
                onPress={() => navigate(tenantPath(`/admin/newsletters/${id}/activity`))}
              >
                Activity Log
              </Button>
            )}
            {nonOpenerCount > 0 && newsletter.status === 'sent' && (
              <Button
                color="secondary"
                variant="flat"
                startContent={<Send size={16} />}
                onPress={() => setResendOpen(true)}
              >
                Resend to {nonOpenerCount.toLocaleString()} Non-Openers
              </Button>
            )}
          </div>
        }
      />

      {/* ── Newsletter Info Card ── */}
      <Card shadow="sm" className="mb-6">
        <CardBody className="flex flex-row flex-wrap items-center justify-between gap-4 p-5">
          <div className="min-w-0 flex-1">
            <h2 className="text-xl font-bold text-foreground">{newsletter.subject}</h2>
            <p className="mt-1 text-sm text-default-500">
              {newsletter.sent_at
                ? `Sent on ${new Date(newsletter.sent_at).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}`
                : `Created ${newsletter.created_at ? new Date(newsletter.created_at).toLocaleDateString() : ''}`}
              {newsletter.author_name ? ` by ${newsletter.author_name}` : ''}
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Chip
              size="sm"
              color={newsletter.status === 'sent' ? 'success' : newsletter.status === 'draft' ? 'default' : 'warning'}
              variant="flat"
            >
              {newsletter.status}
            </Chip>
            {newsletter.ab_test_enabled && (
              <Chip size="sm" color="warning" variant="flat">
                A/B Test
              </Chip>
            )}
          </div>
        </CardBody>
      </Card>

      {/* ── Metrics Row (with unique counts) ── */}
      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="Success Rate"
          value={`${engagement.success_rate}%`}
          icon={CheckCircle}
          color="success"
          description={`${delivery.delivered.toLocaleString()} delivered`}
        />
        <StatCard
          label="Open Rate"
          value={`${engagement.open_rate}%`}
          icon={Eye}
          color="primary"
          description={`${engagement.unique_opens.toLocaleString()} unique opens`}
        />
        <StatCard
          label="Click Rate"
          value={`${engagement.click_rate}%`}
          icon={MousePointer}
          color="warning"
          description={`${engagement.unique_clicks.toLocaleString()} unique clicks`}
        />
        <StatCard
          label="Click-to-Open Rate"
          value={`${engagement.click_to_open_rate}%`}
          icon={BarChart3}
          color="secondary"
          description={`${engagement.total_clicks.toLocaleString()} total clicks`}
        />
      </div>

      {/* ── Engagement Funnel ── */}
      <Card shadow="sm" className="mb-6">
        <CardHeader className="flex flex-row items-center gap-2 px-5 pb-0 pt-5">
          <TrendingUp size={18} className="text-default-400" />
          <h3 className="text-lg font-semibold text-foreground">Engagement Funnel</h3>
        </CardHeader>
        <CardBody className="space-y-4 px-5 pb-5">
          <FunnelBar
            label="Delivered"
            value={delivery.delivered}
            total={delivery.delivered}
            color="primary"
          />
          <FunnelBar
            label="Opened"
            value={engagement.unique_opens}
            total={delivery.delivered}
            color="secondary"
            rate={engagement.open_rate}
          />
          <FunnelBar
            label="Clicked"
            value={engagement.unique_clicks}
            total={delivery.delivered}
            color="success"
            rate={engagement.click_rate}
          />
        </CardBody>
      </Card>

      {/* ── Delivery Stats Card ── */}
      <Card shadow="sm" className="mb-6">
        <CardHeader className="px-5 pb-0 pt-5">
          <h3 className="text-lg font-semibold text-foreground">Delivery Stats</h3>
        </CardHeader>
        <CardBody className="space-y-4 px-5 pb-5">
          <DeliveryBar label="Delivered" value={delivery.delivered} total={delivery.total_sent + delivery.pending} color="success" />
          <DeliveryBar label="Failed" value={delivery.failed} total={delivery.total_sent + delivery.pending} color="danger" />
          <DeliveryBar label="Bounced" value={delivery.bounced} total={delivery.total_sent + delivery.pending} color="warning" />
          <DeliveryBar label="Pending" value={delivery.pending} total={delivery.total_sent + delivery.pending} color="default" />
        </CardBody>
      </Card>

      {/* ── A/B Test Results (conditional) ── */}
      {ab_test && (
        <Card shadow="sm" className="mb-6 border-2 border-warning-200">
          <CardHeader className="flex flex-row items-center gap-3 px-5 pb-0 pt-5">
            <Chip size="sm" color="warning" variant="solid">A/B</Chip>
            <h3 className="text-lg font-semibold text-foreground">Subject Line Test Results</h3>
          </CardHeader>
          <CardBody className="space-y-5 px-5 pb-5">
            {/* Winner Announcement */}
            {ab_test.winner && (
              <div className="flex items-center gap-3 rounded-lg bg-success-50 p-4 dark:bg-success-50/10">
                <Trophy size={24} className="text-success" />
                <div>
                  <p className="font-semibold text-success-700 dark:text-success">
                    Winner: Subject {ab_test.winner.toUpperCase()}
                  </p>
                  <p className="text-sm text-success-600 dark:text-success-400">
                    &quot;{ab_test.winner === 'a' ? ab_test.subject_a : ab_test.subject_b}&quot;
                  </p>
                </div>
              </div>
            )}

            {/* Variant Comparison */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <AbVariantCard
                label="Subject A"
                subject={ab_test.subject_a}
                opens={ab_test.subject_a_opens}
                clicks={ab_test.subject_a_clicks}
                sent={ab_test.subject_a_sent}
                openRate={ab_test.subject_a_open_rate}
                clickRate={ab_test.subject_a_click_rate}
                isWinner={ab_test.winner === 'a'}
                isLeading={!ab_test.winner && ab_test.subject_a_opens > ab_test.subject_b_opens}
                chipColor="primary"
              />
              <AbVariantCard
                label="Subject B"
                subject={ab_test.subject_b}
                opens={ab_test.subject_b_opens}
                clicks={ab_test.subject_b_clicks}
                sent={ab_test.subject_b_sent}
                openRate={ab_test.subject_b_open_rate}
                clickRate={ab_test.subject_b_click_rate}
                isWinner={ab_test.winner === 'b'}
                isLeading={!ab_test.winner && ab_test.subject_b_opens > ab_test.subject_a_opens}
                chipColor="warning"
              />
            </div>

            {/* Split info + Select Winner Buttons */}
            <div className="flex flex-wrap items-center gap-3 border-t border-divider pt-4">
              <span className="text-sm text-default-500">
                Split: {ab_test.split_percentage}% A / {100 - ab_test.split_percentage}% B
                &bull; Winning metric: {ab_test.winner_metric === 'clicks' ? 'Click Rate' : 'Open Rate'}
                {ab_test.winning_margin > 0 && (
                  <> &bull; Margin: {ab_test.winning_margin}%</>
                )}
              </span>
              {!ab_test.winner && (
                <div className="ml-auto flex gap-2">
                  <Button
                    size="sm"
                    color="primary"
                    variant="flat"
                    isLoading={selectingWinner}
                    onPress={() => handleSelectWinner('a')}
                  >
                    Select A as Winner
                  </Button>
                  <Button
                    size="sm"
                    color="warning"
                    variant="flat"
                    isLoading={selectingWinner}
                    onPress={() => handleSelectWinner('b')}
                  >
                    Select B as Winner
                  </Button>
                </div>
              )}
            </div>
          </CardBody>
        </Card>
      )}

      {/* ── Device Breakdown + Engagement Timeline side by side ── */}
      <div className="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Device Breakdown */}
        {deviceTotal > 0 && (
          <Card shadow="sm" className="lg:col-span-1">
            <CardHeader className="flex flex-row items-center gap-2 px-5 pb-0 pt-5">
              <Monitor size={18} className="text-default-400" />
              <h3 className="text-lg font-semibold text-foreground">Devices</h3>
            </CardHeader>
            <CardBody className="px-5 pb-5">
              <div className="mx-auto h-48 w-48">
                <ResponsiveContainer width="100%" height="100%">
                  <PieChart>
                    <Pie
                      data={deviceChartData}
                      cx="50%"
                      cy="50%"
                      innerRadius={40}
                      outerRadius={70}
                      paddingAngle={3}
                      dataKey="value"
                    >
                      {deviceChartData.map((entry, index) => (
                        <Cell key={index} fill={entry.color} />
                      ))}
                    </Pie>
                    <Tooltip
                      formatter={(value: number | undefined, name?: string) => {
                        const v = value ?? 0;
                        return [
                          `${v.toLocaleString()} (${deviceTotal > 0 ? Math.round((v / deviceTotal) * 100) : 0}%)`,
                          name ?? '',
                        ];
                      }}
                    />
                  </PieChart>
                </ResponsiveContainer>
              </div>
              <div className="mt-2 grid grid-cols-2 gap-3">
                {Object.entries(device_stats || {}).map(([device, count]) => {
                  if (count === 0) return null;
                  const Icon = DEVICE_ICONS[device] || HelpCircle;
                  const pct = deviceTotal > 0 ? Math.round((count / deviceTotal) * 100) : 0;
                  return (
                    <div key={device} className="flex items-center gap-2 text-sm">
                      <Icon size={14} style={{ '--device-color': DEVICE_COLORS[device], color: 'var(--device-color)' } as CSSProperties} />
                      <span className="capitalize text-default-600">{device}</span>
                      <span className="ml-auto font-semibold">{pct}%</span>
                    </div>
                  );
                })}
              </div>
            </CardBody>
          </Card>
        )}

        {/* Engagement Timeline */}
        {timeline.length > 0 && (
          <Card shadow="sm" className={deviceTotal > 0 ? 'lg:col-span-2' : 'lg:col-span-3'}>
            <CardHeader className="flex flex-row items-center gap-2 px-5 pb-0 pt-5">
              <Clock size={18} className="text-default-400" />
              <h3 className="text-lg font-semibold text-foreground">Engagement Timeline</h3>
              <span className="ml-auto text-xs text-default-400">First 48 hours after send</span>
            </CardHeader>
            <CardBody className="px-5 pb-5">
              <div className="h-64 w-full">
                <ResponsiveContainer width="100%" height="100%">
                  <LineChart data={timeline} margin={{ top: 5, right: 20, left: 0, bottom: 5 }}>
                    <CartesianGrid strokeDasharray="3 3" className="stroke-default-200" />
                    <XAxis
                      dataKey="hour"
                      tickFormatter={(h: number) => `${h}h`}
                      fontSize={12}
                      className="fill-default-500"
                    />
                    <YAxis fontSize={12} className="fill-default-500" />
                    <Tooltip
                      contentStyle={{
                        backgroundColor: 'hsl(var(--heroui-content1))',
                        borderColor: 'hsl(var(--heroui-divider))',
                        borderRadius: '8px',
                        fontSize: '13px',
                      }}
                      labelFormatter={(h) => `Hour ${h}`}
                    />
                    <Legend />
                    <Line
                      type="monotone"
                      dataKey="opens"
                      name="Opens"
                      stroke="hsl(var(--heroui-primary))"
                      strokeWidth={2}
                      dot={false}
                      activeDot={{ r: 4 }}
                    />
                    <Line
                      type="monotone"
                      dataKey="clicks"
                      name="Clicks"
                      stroke="hsl(var(--heroui-success))"
                      strokeWidth={2}
                      dot={false}
                      activeDot={{ r: 4 }}
                    />
                  </LineChart>
                </ResponsiveContainer>
              </div>
              <Divider className="my-3" />
              <p className="text-center text-sm text-default-400">
                Total opens: {engagement.total_opens.toLocaleString()} | Total clicks: {engagement.total_clicks.toLocaleString()}
                {peak_engagement && peak_engagement.max_opens_per_hour > 0 && (
                  <> | Peak: {peak_engagement.max_opens_per_hour} opens at hour {peak_engagement.peak_hour}</>
                )}
              </p>
            </CardBody>
          </Card>
        )}
      </div>

      {/* ── Email Client Breakdown ── */}
      {emailClients.length > 0 && (
        <Card shadow="sm" className="mb-6">
          <CardHeader className="flex flex-row items-center gap-2 px-5 pb-0 pt-5">
            <Mail size={18} className="text-default-400" />
            <h3 className="text-lg font-semibold text-foreground">Email Clients</h3>
          </CardHeader>
          <CardBody className="px-5 pb-5">
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
              {emailClients.map((ec) => {
                const ecTotal = emailClients.reduce((s, c) => s + c.count, 0);
                const pct = ecTotal > 0 ? Math.round((ec.count / ecTotal) * 100) : 0;
                return (
                  <div key={ec.client} className="text-center">
                    <p className="text-2xl font-bold text-foreground">{pct}%</p>
                    <p className="text-sm text-default-500">{ec.client}</p>
                    <p className="text-xs text-default-400">{ec.count.toLocaleString()} opens</p>
                  </div>
                );
              })}
            </div>
          </CardBody>
        </Card>
      )}

      {/* ── Top Links ── */}
      {top_links.length > 0 && (
        <Card shadow="sm" className="mb-6">
          <CardHeader className="flex flex-row items-center gap-2 px-5 pb-0 pt-5">
            <ExternalLink size={18} className="text-default-400" />
            <h3 className="text-lg font-semibold text-foreground">Top Clicked Links</h3>
          </CardHeader>
          <CardBody className="px-5 pb-5">
            <Table
              aria-label="Top clicked links"
              removeWrapper
              classNames={{ th: 'text-default-500 text-xs uppercase' }}
            >
              <TableHeader>
                <TableColumn>URL</TableColumn>
                <TableColumn align="end">Clicks</TableColumn>
                <TableColumn align="end">Unique</TableColumn>
              </TableHeader>
              <TableBody>
                {top_links.map((link, i) => (
                  <TableRow key={i}>
                    <TableCell>
                      <a
                        href={link.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="break-all text-sm text-primary hover:underline"
                      >
                        {link.url.length > 80 ? link.url.substring(0, 80) + '...' : link.url}
                      </a>
                    </TableCell>
                    <TableCell>
                      <span className="font-semibold">{link.clicks.toLocaleString()}</span>
                    </TableCell>
                    <TableCell>
                      <span className="text-default-500">{link.unique_clicks.toLocaleString()}</span>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardBody>
        </Card>
      )}

      {/* ── Recent Activity (inline) ── */}
      {recent_activity && recent_activity.length > 0 && (
        <Card shadow="sm" className="mb-6">
          <CardHeader className="flex flex-row items-center justify-between px-5 pb-0 pt-5">
            <div className="flex items-center gap-2">
              <BarChart3 size={18} className="text-default-400" />
              <h3 className="text-lg font-semibold text-foreground">Recent Activity</h3>
            </div>
            {newsletter.status === 'sent' && (
              <Button
                size="sm"
                variant="light"
                onPress={() => navigate(tenantPath(`/admin/newsletters/${id}/activity`))}
              >
                View All
              </Button>
            )}
          </CardHeader>
          <CardBody className="px-5 pb-5">
            <div className="max-h-80 overflow-y-auto">
              <Table
                aria-label="Recent newsletter activity"
                removeWrapper
                classNames={{ th: 'text-default-500 text-xs uppercase', td: 'py-2' }}
              >
                <TableHeader>
                  <TableColumn>Event</TableColumn>
                  <TableColumn>Email</TableColumn>
                  <TableColumn>Time</TableColumn>
                </TableHeader>
                <TableBody>
                  {recent_activity.map((item, i) => (
                    <TableRow key={i}>
                      <TableCell>
                        <Chip
                          size="sm"
                          variant="flat"
                          color={item.action_type === 'open' ? 'primary' : 'success'}
                        >
                          {item.action_type === 'open' ? 'Opened' : 'Clicked'}
                        </Chip>
                      </TableCell>
                      <TableCell>
                        <div>
                          <span className="text-sm">{item.email}</span>
                          {item.action_type === 'click' && item.url && (
                            <div className="mt-0.5 max-w-xs truncate text-xs text-default-400">
                              {item.url}
                            </div>
                          )}
                        </div>
                      </TableCell>
                      <TableCell>
                        <span className="text-sm text-default-500">
                          {new Date(item.action_at).toLocaleString(undefined, {
                            month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
                          })}
                        </span>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </CardBody>
        </Card>
      )}

      {/* ── Quick Actions ── */}
      <Card shadow="sm" className="mb-6">
        <CardHeader className="px-5 pb-0 pt-5">
          <h3 className="text-lg font-semibold text-foreground">Actions</h3>
        </CardHeader>
        <CardBody className="px-5 pb-5">
          <div className="flex flex-wrap gap-3">
            <Button
              variant="flat"
              startContent={<FileText size={16} />}
              onPress={() => {
                window.open(`/api/v2/admin/newsletters/${id}/preview`, '_blank');
              }}
            >
              View Email Content
            </Button>
            <Button
              variant="flat"
              startContent={<Copy size={16} />}
              onPress={async () => {
                try {
                  const res = await adminNewsletters.duplicateNewsletter(Number(id));
                  if (res.success) {
                    toast.success('Newsletter duplicated as draft');
                    navigate(tenantPath('/admin/newsletters'));
                  } else {
                    toast.error('Failed to duplicate');
                  }
                } catch {
                  toast.error('Failed to duplicate');
                }
              }}
            >
              Duplicate Newsletter
            </Button>
            {newsletter.status === 'sent' && (
              <Button
                variant="flat"
                startContent={<BarChart3 size={16} />}
                onPress={() => navigate(tenantPath(`/admin/newsletters/${id}/activity`))}
              >
                Full Activity Log
              </Button>
            )}
          </div>
        </CardBody>
      </Card>

      {/* ── Resend Modal ── */}
      {resendOpen && (
        <NewsletterResend
          isOpen={resendOpen}
          onClose={() => setResendOpen(false)}
          newsletterId={Number(id)}
          onSuccess={loadStats}
        />
      )}
    </div>
  );
}

// ─── Sub-Components ─────────────────────────────────────────────────────────

function FunnelBar({
  label,
  value,
  total,
  color,
  rate,
}: {
  label: string;
  value: number;
  total: number;
  color: 'primary' | 'secondary' | 'success';
  rate?: number;
}) {
  const pct = total > 0 ? (value / total) * 100 : 0;
  return (
    <div>
      <div className="mb-1 flex items-center justify-between text-sm">
        <span className="text-default-600">{label}</span>
        <span className="font-semibold text-foreground">
          {value.toLocaleString()}
          {rate !== undefined && <span className="ml-1 text-default-400">({rate}%)</span>}
        </span>
      </div>
      <Progress
        value={pct}
        color={color}
        size="md"
        aria-label={`${label}: ${value}`}
        className="h-3"
      />
    </div>
  );
}

function DeliveryBar({
  label,
  value,
  total,
  color,
}: {
  label: string;
  value: number;
  total: number;
  color: 'success' | 'danger' | 'warning' | 'default';
}) {
  const pct = total > 0 ? (value / total) * 100 : 0;
  return (
    <div>
      <div className="mb-1 flex items-center justify-between text-sm">
        <span className="text-default-600">{label}</span>
        <span className="font-semibold text-foreground">{value.toLocaleString()}</span>
      </div>
      <Progress
        value={pct}
        color={color}
        size="sm"
        aria-label={`${label}: ${value}`}
      />
    </div>
  );
}

function AbVariantCard({
  label,
  subject,
  opens,
  clicks,
  sent,
  openRate,
  clickRate,
  isWinner,
  isLeading,
  chipColor,
}: {
  label: string;
  subject: string;
  opens: number;
  clicks: number;
  sent: number;
  openRate: number;
  clickRate: number;
  isWinner: boolean;
  isLeading: boolean;
  chipColor: 'primary' | 'warning';
}) {
  return (
    <Card
      shadow="none"
      className={`border-2 ${isWinner ? 'border-success bg-success-50/50 dark:bg-success-50/5' : 'border-default-200'}`}
    >
      <CardBody className="space-y-3 p-4">
        <div className="flex items-center justify-between">
          <Chip size="sm" color={chipColor} variant="solid">{label}</Chip>
          {isWinner && (
            <div className="flex items-center gap-1 text-success">
              <Trophy size={14} />
              <span className="text-xs font-semibold">Winner</span>
            </div>
          )}
          {isLeading && !isWinner && (
            <span className="text-xs font-medium text-success">Leading</span>
          )}
        </div>
        <p className="text-sm italic text-default-600">&quot;{subject}&quot;</p>
        <div className="grid grid-cols-3 gap-3 text-center">
          <div>
            <p className="text-2xl font-bold text-primary">{openRate}%</p>
            <p className="text-xs text-default-500">Open Rate</p>
          </div>
          <div>
            <p className="text-2xl font-bold text-success">{clickRate}%</p>
            <p className="text-xs text-default-500">Click Rate</p>
          </div>
          <div>
            <p className="text-2xl font-bold text-foreground">{sent.toLocaleString()}</p>
            <p className="text-xs text-default-500">Sent</p>
          </div>
        </div>
        <div className="flex justify-center gap-4 border-t border-divider pt-2 text-xs text-default-400">
          <span>{opens.toLocaleString()} opens</span>
          <span>{clicks.toLocaleString()} clicks</span>
        </div>
      </CardBody>
    </Card>
  );
}

export default NewsletterStats;
