// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter Stats
 * Detailed per-campaign statistics page for a single newsletter.
 * Shows delivery, engagement, A/B test results, timeline, and top links.
 */

import { useState, useCallback, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card, CardBody, CardHeader, Button, Chip, Progress, Skeleton,
  Table, TableHeader, TableColumn, TableBody, TableRow, TableCell,
  Divider,
} from '@heroui/react';
import {
  ArrowLeft, CheckCircle, Eye, MousePointer, BarChart3,
  Trophy, Send, Mail, ExternalLink, Clock,
} from 'lucide-react';
import {
  ResponsiveContainer, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
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

interface StatsData {
  newsletter: NewsletterInfo;
  delivery: DeliveryStats;
  engagement: EngagementStats;
  ab_test: AbTestData | null;
  timeline: TimelinePoint[];
  top_links: TopLink[];
}

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

  const loadStats = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await adminNewsletters.getStats(Number(id));
      if (res.success && res.data) {
        setData(res.data as unknown as StatsData);
      } else {
        setError('Newsletter not found');
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

  const { newsletter, delivery, engagement, ab_test, timeline, top_links } = data;
  const nonOpenerCount = delivery.delivered - engagement.unique_opens;

  return (
    <div>
      {/* ── Header ── */}
      <PageHeader
        title={newsletter.subject || newsletter.name}
        description="Campaign performance metrics"
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/newsletters'))}
            >
              Back
            </Button>
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

      {/* ── Metrics Row ── */}
      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="Success Rate"
          value={`${engagement.success_rate}%`}
          icon={CheckCircle}
          color="success"
        />
        <StatCard
          label="Open Rate"
          value={`${engagement.open_rate}%`}
          icon={Eye}
          color="primary"
        />
        <StatCard
          label="Click Rate"
          value={`${engagement.click_rate}%`}
          icon={MousePointer}
          color="warning"
        />
        <StatCard
          label="Click-to-Open Rate"
          value={`${engagement.click_to_open_rate}%`}
          icon={BarChart3}
          color="secondary"
        />
      </div>

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
                isWinner={ab_test.winner === 'a'}
                isLeading={!ab_test.winner && ab_test.subject_a_opens > ab_test.subject_b_opens}
                chipColor="primary"
              />
              <AbVariantCard
                label="Subject B"
                subject={ab_test.subject_b}
                opens={ab_test.subject_b_opens}
                clicks={ab_test.subject_b_clicks}
                isWinner={ab_test.winner === 'b'}
                isLeading={!ab_test.winner && ab_test.subject_b_opens > ab_test.subject_a_opens}
                chipColor="warning"
              />
            </div>

            {/* Select Winner Buttons */}
            {!ab_test.winner && (
              <div className="flex flex-wrap items-center gap-3 border-t border-divider pt-4">
                <span className="text-sm text-default-500">
                  Winning margin: {ab_test.winning_margin}%
                </span>
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
              </div>
            )}
          </CardBody>
        </Card>
      )}

      {/* ── Engagement Timeline ── */}
      {timeline.length > 0 && (
        <Card shadow="sm" className="mb-6">
          <CardHeader className="flex flex-row items-center gap-2 px-5 pb-0 pt-5">
            <Clock size={18} className="text-default-400" />
            <h3 className="text-lg font-semibold text-foreground">Engagement Timeline</h3>
            <span className="ml-auto text-xs text-default-400">First 48 hours after send</span>
          </CardHeader>
          <CardBody className="px-5 pb-5">
            <div className="h-72 w-full">
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
            </p>
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
                        className="text-sm text-primary hover:underline break-all"
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
  isWinner,
  isLeading,
  chipColor,
}: {
  label: string;
  subject: string;
  opens: number;
  clicks: number;
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
        <div className="grid grid-cols-2 gap-3 text-center">
          <div>
            <p className="text-2xl font-bold text-primary">{opens.toLocaleString()}</p>
            <p className="text-xs text-default-500">Opens</p>
          </div>
          <div>
            <p className="text-2xl font-bold text-success">{clicks.toLocaleString()}</p>
            <p className="text-xs text-default-500">Clicks</p>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

export default NewsletterStats;
