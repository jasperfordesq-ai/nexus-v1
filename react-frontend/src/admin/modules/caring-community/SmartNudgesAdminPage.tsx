// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SmartNudgesAdminPage — AG31
 *
 * Admin console for the smart-nudge engine that suggests tandem matches
 * to members who would benefit from a peer connection.
 *
 * - 30-day conversion analytics chart
 * - Config (enabled, min_score threshold, cooldown days, daily limit)
 * - Dry-run dispatch preview (no emails sent)
 * - Real dispatch with confirm modal
 *
 * Admin English only — no t() calls.
 */

import { useCallback, useEffect, useState } from 'react';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Spinner,
  Switch,
  useDisclosure,
} from '@heroui/react';
import Bell from 'lucide-react/icons/bell';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Save from 'lucide-react/icons/save';
import Send from 'lucide-react/icons/send';
import FlaskConical from 'lucide-react/icons/flask-conical';
import TrendingUp from 'lucide-react/icons/trending-up';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { PageHeader, StatCard } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface NudgeConfig {
  enabled: boolean;
  min_score: number;
  cooldown_days: number;
  daily_limit: number;
}

interface NudgeStats {
  sent_total: number;
  sent_30d: number;
  converted_total: number;
  converted_30d: number;
  conversion_rate_30d: number;
  opted_out_members: number;
}

interface NudgeRecent {
  id: number;
  target_user: { id: number; name: string };
  related_user: { id: number; name: string };
  score: number;
  status: string;
  sent_at: string;
  converted_at: string | null;
}

interface NudgeAnalytics {
  config: NudgeConfig;
  stats: NudgeStats;
  recent: NudgeRecent[];
  eligible_candidates: number;
}

interface DispatchResult {
  dispatched?: number;
  skipped?: number;
  candidates?: Array<{ target_user_id: number; related_user_id: number; score: number }>;
  dry_run?: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function buildChartData(recent: NudgeRecent[]) {
  // Group by day for last 30 days
  const days: Record<string, { date: string; sent: number; converted: number }> = {};
  const now = new Date();
  for (let i = 29; i >= 0; i--) {
    const d = new Date(now);
    d.setDate(d.getDate() - i);
    const key = d.toISOString().slice(0, 10);
    days[key] = { date: key.slice(5), sent: 0, converted: 0 };
  }
  for (const r of recent) {
    const sentKey = r.sent_at?.slice(0, 10);
    if (sentKey && days[sentKey]) days[sentKey].sent++;
    if (r.converted_at) {
      const convKey = r.converted_at.slice(0, 10);
      if (days[convKey]) days[convKey].converted++;
    }
  }
  return Object.values(days);
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function SmartNudgesAdminPage() {
  const toast = useToast();
  usePageTitle('Smart member nudges');

  const [data, setData] = useState<NudgeAnalytics | null>(null);
  const [config, setConfig] = useState<NudgeConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [savingConfig, setSavingConfig] = useState(false);
  const [running, setRunning] = useState(false);
  const [dryResult, setDryResult] = useState<DispatchResult | null>(null);
  const { isOpen, onOpen, onClose } = useDisclosure();

  const load = useCallback(async () => {
    setRefreshing(true);
    try {
      const res = await api.get<NudgeAnalytics>('/v2/admin/caring-community/nudges/analytics');
      if (res.success && res.data) {
        setData(res.data);
        setConfig(res.data.config);
      } else {
        toast.error(res.error || 'Failed to load nudge analytics');
      }
    } catch (err) {
      logError('SmartNudgesAdminPage: load failed', err);
      toast.error('Failed to load nudge analytics');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [toast]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleSaveConfig = useCallback(async () => {
    if (!config) return;
    setSavingConfig(true);
    try {
      const res = await api.put<{ config: NudgeConfig }>(
        '/v2/admin/caring-community/nudges/config',
        config,
      );
      if (res.success) {
        toast.success('Nudge configuration saved');
        void load();
      } else {
        toast.error(res.error || 'Failed to save configuration');
      }
    } catch (err) {
      logError('SmartNudgesAdminPage: save config failed', err);
      toast.error('Failed to save configuration');
    } finally {
      setSavingConfig(false);
    }
  }, [config, toast, load]);

  const handleDispatch = useCallback(
    async (dryRun: boolean) => {
      setRunning(true);
      try {
        const res = await api.post<DispatchResult>('/v2/admin/caring-community/nudges/dispatch', {
          dry_run: dryRun,
        });
        if (res.success && res.data) {
          if (dryRun) {
            setDryResult(res.data);
            toast.success(`Dry run: ${res.data.candidates?.length ?? 0} eligible candidates`);
          } else {
            toast.success(`Dispatched ${res.data.dispatched ?? 0} nudges`);
            setDryResult(null);
            onClose();
            void load();
          }
        } else {
          toast.error(res.error || 'Dispatch failed');
        }
      } catch (err) {
        logError('SmartNudgesAdminPage: dispatch failed', err);
        toast.error('Dispatch failed');
      } finally {
        setRunning(false);
      }
    },
    [toast, onClose, load],
  );

  if (loading || !data || !config) {
    return (
      <div className="flex items-center justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  const stats = data.stats;
  const chartData = buildChartData(data.recent);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Smart member nudges"
        description="Automated suggestions that nudge members toward high-fit tandem partners. Conservative defaults — runs on a daily cap."
        actions={
          <div className="flex flex-wrap gap-2">
            <Button
              size="sm"
              variant="bordered"
              startContent={<RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />}
              onPress={() => void load()}
              isDisabled={refreshing}
            >
              Refresh
            </Button>
            <Button
              size="sm"
              variant="flat"
              color="secondary"
              startContent={<FlaskConical className="w-4 h-4" />}
              onPress={() => void handleDispatch(true)}
              isLoading={running}
            >
              Dry-run
            </Button>
            <Button
              size="sm"
              color="primary"
              startContent={<Send className="w-4 h-4" />}
              onPress={onOpen}
              isDisabled={!config.enabled}
            >
              Dispatch now
            </Button>
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
                Smart Nudges are automated prompts sent to members when a high-fit tandem-match partner is detected.
                Members who would benefit from a peer connection are identified by the matching engine and sent an
                in-app notification (and optionally email) suggesting they connect. Nudges respect a cooldown window
                so no member is contacted too frequently. Use the dry-run to preview who would be nudged before
                dispatching for real.
              </p>
              <ul className="mt-1 space-y-0.5 text-default-500 list-disc list-inside">
                <li><span className="font-medium text-default-600">Inactivity:</span> triggered when a member hasn't logged in for the configured number of days</li>
                <li><span className="font-medium text-default-600">Unmatched request:</span> triggered when a help request has no offers after the cooldown window passes</li>
                <li><span className="font-medium text-default-600">Follow-up:</span> sent to the coordinator after an approved exchange hasn't been marked complete</li>
              </ul>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <StatCard icon={Bell} label="Sent (30d)" value={String(stats.sent_30d)} color="primary" />
        <StatCard
          icon={TrendingUp}
          label="Conversion (30d)"
          value={`${(stats.conversion_rate_30d * 100).toFixed(1)}%`}
          color="success"
        />
        <StatCard icon={Bell} label="Eligible candidates" value={String(data.eligible_candidates)} color="warning" />
        <StatCard icon={Bell} label="Opted out" value={String(stats.opted_out_members)} color="default" />
      </div>

      {/* Chart */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <TrendingUp className="w-5 h-5 text-primary" />
          <h2 className="text-base font-semibold">Daily nudges (last 30 days)</h2>
        </CardHeader>
        <Divider />
        <CardBody>
          <div className="w-full h-72">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={chartData} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e4e4e7" />
                <XAxis dataKey="date" tick={{ fontSize: 11 }} interval={3} />
                <YAxis tick={{ fontSize: 11 }} allowDecimals={false} />
                <Tooltip />
                <Legend />
                <Bar dataKey="sent" fill="#3b82f6" name="Sent" />
                <Bar dataKey="converted" fill="#22c55e" name="Converted" />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </CardBody>
      </Card>

      {/* Config */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Bell className="w-5 h-5 text-warning" />
          <h2 className="text-base font-semibold">Configuration</h2>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium">Engine enabled</p>
              <p className="text-xs text-default-500">When off, no nudges are dispatched.</p>
            </div>
            <Switch
              isSelected={config.enabled}
              onValueChange={(v) => setConfig({ ...config, enabled: v })}
              color="success"
            />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Input
              label="Minimum score (0.40 - 0.95)"
              type="number"
              min="0.40"
              max="0.95"
              step="0.05"
              value={String(config.min_score)}
              onValueChange={(v) =>
                setConfig({ ...config, min_score: Math.max(0.4, Math.min(0.95, parseFloat(v) || 0.55)) })
              }
              description="Tandem-match score threshold for sending a nudge"
            />
            <Input
              label="Cooldown (days)"
              type="number"
              min="1"
              max="90"
              step="1"
              value={String(config.cooldown_days)}
              onValueChange={(v) =>
                setConfig({ ...config, cooldown_days: Math.max(1, Math.min(90, parseInt(v, 10) || 14)) })
              }
              description="Wait this long before nudging the same pair again"
            />
            <Input
              label="Daily limit"
              type="number"
              min="1"
              max="250"
              step="1"
              value={String(config.daily_limit)}
              onValueChange={(v) =>
                setConfig({ ...config, daily_limit: Math.max(1, Math.min(250, parseInt(v, 10) || 25)) })
              }
              description="Maximum nudges per day across the whole tenant"
            />
          </div>

          <div className="flex flex-col items-end gap-1">
            <Button
              color="primary"
              startContent={<Save className="w-4 h-4" />}
              onPress={() => void handleSaveConfig()}
              isLoading={savingConfig}
            >
              Save configuration
            </Button>
            <p className="text-xs text-default-400">
              Changes take effect immediately — the next scheduled nudge run will apply the new settings.
            </p>
          </div>
        </CardBody>
      </Card>

      {/* Dry-run result */}
      {dryResult && (
        <Card>
          <CardHeader className="flex items-center gap-2">
            <FlaskConical className="w-5 h-5 text-secondary" />
            <h2 className="text-base font-semibold">Dry-run preview</h2>
            <Chip size="sm" variant="flat" className="ml-auto">
              {dryResult.candidates?.length ?? 0} candidates
            </Chip>
          </CardHeader>
          <Divider />
          <CardBody>
            {!dryResult.candidates || dryResult.candidates.length === 0 ? (
              <p className="text-sm text-default-500">No eligible candidates right now.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-default-50">
                    <tr className="text-xs text-default-500 uppercase tracking-wide">
                      <th className="text-left px-3 py-2">Target user</th>
                      <th className="text-left px-3 py-2">Suggested partner</th>
                      <th className="text-right px-3 py-2">Score</th>
                    </tr>
                  </thead>
                  <tbody>
                    {dryResult.candidates.map((c, i) => (
                      <tr key={i} className="border-t border-default-200">
                        <td className="px-3 py-2">#{c.target_user_id}</td>
                        <td className="px-3 py-2">#{c.related_user_id}</td>
                        <td className="px-3 py-2 text-right tabular-nums">{c.score.toFixed(3)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardBody>
        </Card>
      )}

      {/* Recent nudges */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Bell className="w-5 h-5 text-default-500" />
          <h2 className="text-base font-semibold">Recent nudges</h2>
          <Chip size="sm" variant="flat" className="ml-auto">
            {data.recent.length}
          </Chip>
        </CardHeader>
        <Divider />
        <CardBody className="p-0">
          {data.recent.length === 0 ? (
            <div className="text-center py-12 text-sm text-default-500">No nudges sent yet.</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-default-50">
                  <tr className="text-xs text-default-500 uppercase tracking-wide">
                    <th className="text-left px-3 py-2">Sent</th>
                    <th className="text-left px-3 py-2">Target</th>
                    <th className="text-left px-3 py-2">Suggested partner</th>
                    <th className="text-right px-3 py-2">Score</th>
                    <th className="text-left px-3 py-2">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {data.recent.map((r) => (
                    <tr key={r.id} className="border-t border-default-200">
                      <td className="px-3 py-2">{new Date(r.sent_at).toLocaleDateString()}</td>
                      <td className="px-3 py-2">{r.target_user.name || `#${r.target_user.id}`}</td>
                      <td className="px-3 py-2">{r.related_user.name || `#${r.related_user.id}`}</td>
                      <td className="px-3 py-2 text-right tabular-nums">{r.score.toFixed(3)}</td>
                      <td className="px-3 py-2">
                        <Chip
                          size="sm"
                          variant="flat"
                          color={r.status === 'converted' ? 'success' : 'default'}
                        >
                          {r.status}
                        </Chip>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>

      {/* Confirm dispatch modal */}
      <Modal isOpen={isOpen} onClose={onClose}>
        <ModalContent>
          <ModalHeader>Dispatch nudges now?</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-600">
              This will send up to <strong>{config.daily_limit}</strong> nudge notifications to eligible
              members right now. Make sure you've reviewed a dry-run first.
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose}>
              Cancel
            </Button>
            <Button color="primary" onPress={() => void handleDispatch(false)} isLoading={running}>
              Dispatch
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
