// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * RegionalPointsAdminPage — AG28 admin console for the regional points
 * (third currency) programme.
 *
 * - Toggle programme on/off and configure label/symbol/auto-issue
 * - View tenant-wide ledger and stats
 * - Manually issue / adjust points for a member
 *
 * Admin English only — no t() calls.
 */

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Spinner,
  Switch,
  Tabs,
  Tab,
  Textarea,
} from '@heroui/react';
import Coins from 'lucide-react/icons/coins';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Save from 'lucide-react/icons/save';
import Plus from 'lucide-react/icons/plus';
import SlidersHorizontal from 'lucide-react/icons/sliders-horizontal';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { MemberSearchPicker, PageHeader, StatCard, type MemberSearchMember } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface RegionalPointsConfig {
  enabled: boolean;
  label: string;
  symbol: string;
  auto_issue_enabled: boolean;
  points_per_approved_hour: number;
  member_transfers_enabled: boolean;
  marketplace_redemption_enabled: boolean;
}

interface LedgerRow {
  id: number;
  user_id: number;
  user_name?: string;
  type: string;
  direction: string;
  points: number;
  balance_after: number;
  description: string | null;
  created_at: string;
}

interface LedgerResponse {
  stats: {
    total_accounts?: number;
    total_balance?: number;
    total_issued?: number;
    total_spent?: number;
    transactions_30d?: number;
  };
  items: LedgerRow[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function RegionalPointsAdminPage() {
  const toast = useToast();
  usePageTitle('Regional Points');

  const [config, setConfig] = useState<RegionalPointsConfig | null>(null);
  const [ledger, setLedger] = useState<LedgerResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [savingConfig, setSavingConfig] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  // Issue/adjust form
  const [member, setMember] = useState<MemberSearchMember | null>(null);
  const [memberQuery, setMemberQuery] = useState('');
  const [issuePoints, setIssuePoints] = useState('');
  const [issueDescription, setIssueDescription] = useState('');
  const [adjustDelta, setAdjustDelta] = useState('');
  const [adjustDescription, setAdjustDescription] = useState('');
  const [submittingIssue, setSubmittingIssue] = useState(false);
  const [submittingAdjust, setSubmittingAdjust] = useState(false);

  const loadAll = useCallback(async () => {
    setRefreshing(true);
    try {
      const [cfgRes, ledRes] = await Promise.all([
        api.get<RegionalPointsConfig>('/v2/admin/caring-community/regional-points/config'),
        api.get<LedgerResponse>('/v2/admin/caring-community/regional-points/ledger?limit=100'),
      ]);
      if (cfgRes.success && cfgRes.data) setConfig(cfgRes.data);
      else if (cfgRes.error) toast.error(cfgRes.error);
      if (ledRes.success && ledRes.data) setLedger(ledRes.data);
      // ledger may 403 if disabled — that's fine, show empty
    } catch (err) {
      logError('RegionalPointsAdminPage: load failed', err);
      toast.error('Failed to load regional points');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [toast]);

  useEffect(() => {
    void loadAll();
  }, [loadAll]);

  const handleSaveConfig = useCallback(async () => {
    if (!config) return;
    setSavingConfig(true);
    try {
      const res = await api.put<RegionalPointsConfig>(
        '/v2/admin/caring-community/regional-points/config',
        config,
      );
      if (res.success && res.data) {
        setConfig(res.data);
        toast.success('Configuration saved');
        void loadAll();
      } else {
        toast.error(res.error || 'Failed to save configuration');
      }
    } catch (err) {
      logError('RegionalPointsAdminPage: save config failed', err);
      toast.error('Failed to save configuration');
    } finally {
      setSavingConfig(false);
    }
  }, [config, toast, loadAll]);

  const handleIssue = useCallback(async () => {
    if (!member) {
      toast.error('Pick a member first');
      return;
    }
    const amount = parseFloat(issuePoints);
    if (!amount || amount <= 0) {
      toast.error('Enter a positive amount');
      return;
    }
    setSubmittingIssue(true);
    try {
      const res = await api.post('/v2/admin/caring-community/regional-points/issue', {
        user_id: member.id,
        points: amount,
        description: issueDescription.trim(),
      });
      if (res.success) {
        toast.success(`Issued ${amount} points to ${member.name}`);
        setIssuePoints('');
        setIssueDescription('');
        void loadAll();
      } else {
        toast.error(res.error || 'Failed to issue points');
      }
    } catch (err) {
      logError('RegionalPointsAdminPage: issue failed', err);
      toast.error('Failed to issue points');
    } finally {
      setSubmittingIssue(false);
    }
  }, [member, issuePoints, issueDescription, toast, loadAll]);

  const handleAdjust = useCallback(async () => {
    if (!member) {
      toast.error('Pick a member first');
      return;
    }
    const delta = parseFloat(adjustDelta);
    if (!delta || delta === 0) {
      toast.error('Enter a non-zero delta (positive or negative)');
      return;
    }
    setSubmittingAdjust(true);
    try {
      const res = await api.post('/v2/admin/caring-community/regional-points/adjust', {
        user_id: member.id,
        points_delta: delta,
        description: adjustDescription.trim(),
      });
      if (res.success) {
        toast.success(`Adjusted ${member.name} by ${delta} points`);
        setAdjustDelta('');
        setAdjustDescription('');
        void loadAll();
      } else {
        toast.error(res.error || 'Failed to adjust points');
      }
    } catch (err) {
      logError('RegionalPointsAdminPage: adjust failed', err);
      toast.error('Failed to adjust points');
    } finally {
      setSubmittingAdjust(false);
    }
  }, [member, adjustDelta, adjustDescription, toast, loadAll]);

  if (loading || !config) {
    return (
      <div className="flex items-center justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  const stats = ledger?.stats ?? {};
  const items = ledger?.items ?? [];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Regional Points"
        description="Third-currency programme — runs alongside time credits and the marketplace currency. Disabled by default."
        actions={
          <Button
            size="sm"
            variant="bordered"
            startContent={<RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />}
            onPress={() => void loadAll()}
            isDisabled={refreshing}
          >
            Refresh
          </Button>
        }
      />

      {/* Intro card */}
      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                Regional Points are supplementary credits used to recognise contributions that don't
                fit the standard hour exchange model — for example, attending community events,
                participating in surveys, or completing training. They are distinct from time credits
                (care hours) and cannot be converted to them. Use them to encourage broader community
                participation beyond direct care.
              </p>
              <p className="text-default-500">
                Allocate points manually to individual members using the issue or adjust tools below.
                Points appear in the member's wallet alongside their time credit balance. Members can
                redeem Regional Points for local rewards defined in the Loyalty programme. Points
                expire after 12 months unless the expiry setting is set to 0 (never expire).
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <StatCard
          icon={Coins}
          label="Total balance issued"
          value={Number(stats.total_balance ?? 0).toFixed(2)}
          color="warning"
        />
        <StatCard
          icon={Plus}
          label="Lifetime issued"
          value={Number(stats.total_issued ?? 0).toFixed(2)}
          color="success"
        />
        <StatCard
          icon={SlidersHorizontal}
          label="Lifetime spent"
          value={Number(stats.total_spent ?? 0).toFixed(2)}
          color="primary"
        />
        <StatCard
          icon={Coins}
          label="Member accounts"
          value={String(stats.total_accounts ?? 0)}
          color="default"
        />
      </div>

      {/* Config */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <SlidersHorizontal className="w-5 h-5 text-primary" />
          <h2 className="text-base font-semibold">Programme configuration</h2>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium">Programme enabled</p>
              <p className="text-xs text-default-500">
                When off, the third currency is hidden from members and admin endpoints return 403.
              </p>
            </div>
            <Switch
              isSelected={config.enabled}
              onValueChange={(v) => setConfig({ ...config, enabled: v })}
              color="success"
            />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Input
              label="Display label"
              value={config.label}
              onValueChange={(v) => setConfig({ ...config, label: v })}
              description="Shown in the wallet (e.g. 'Regional Points', 'Stadtmünze')"
            />
            <Input
              label="Symbol"
              value={config.symbol}
              onValueChange={(v) => setConfig({ ...config, symbol: v })}
              description="Short suffix shown next to amounts (e.g. 'pts', 'SM')"
            />
          </div>

          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium">Auto-issue on approved hours</p>
              <p className="text-xs text-default-500">
                When a verified hour is logged, automatically award this many regional points.
              </p>
            </div>
            <Switch
              isSelected={config.auto_issue_enabled}
              onValueChange={(v) => setConfig({ ...config, auto_issue_enabled: v })}
              color="success"
            />
          </div>

          <Input
            label="Points per approved hour"
            type="number"
            value={String(config.points_per_approved_hour)}
            onValueChange={(v) =>
              setConfig({ ...config, points_per_approved_hour: parseFloat(v) || 0 })
            }
            min="0"
            step="0.5"
            isDisabled={!config.auto_issue_enabled}
          />

          <Divider />

          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium">Member-to-member transfers</p>
              <p className="text-xs text-default-500">Allow members to send points to each other.</p>
            </div>
            <Switch
              isSelected={config.member_transfers_enabled}
              onValueChange={(v) => setConfig({ ...config, member_transfers_enabled: v })}
              color="success"
            />
          </div>

          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium">Marketplace redemption</p>
              <p className="text-xs text-default-500">
                Allow members to redeem points as a CHF discount on participating sellers.
              </p>
            </div>
            <Switch
              isSelected={config.marketplace_redemption_enabled}
              onValueChange={(v) =>
                setConfig({ ...config, marketplace_redemption_enabled: v })
              }
              color="success"
            />
          </div>

          <div className="flex justify-end">
            <Button
              color="primary"
              startContent={<Save className="w-4 h-4" />}
              onPress={() => void handleSaveConfig()}
              isLoading={savingConfig}
            >
              Save configuration
            </Button>
          </div>
        </CardBody>
      </Card>

      {/* Issue / Adjust */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Plus className="w-5 h-5 text-success" />
          <h2 className="text-base font-semibold">Issue / adjust member balance</h2>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <MemberSearchPicker
            label="Member"
            placeholder="Search by name or email"
            value={memberQuery}
            onValueChange={setMemberQuery}
            selectedMember={member}
            onSelectedMemberChange={setMember}
            noResultsText="No matching members"
            clearText="Clear"
          />

          {member && (
            <Tabs aria-label="Action">
              <Tab key="issue" title="Issue points">
                <div className="space-y-3 pt-3">
                  <Input
                    label="Points to issue"
                    type="number"
                    min="0"
                    step="0.5"
                    value={issuePoints}
                    onValueChange={setIssuePoints}
                  />
                  <Textarea
                    label="Description"
                    minRows={2}
                    value={issueDescription}
                    onValueChange={setIssueDescription}
                  />
                  <div className="flex justify-end">
                    <Button
                      color="primary"
                      onPress={() => void handleIssue()}
                      isLoading={submittingIssue}
                    >
                      Issue points
                    </Button>
                  </div>
                </div>
              </Tab>
              <Tab key="adjust" title="Adjust (+/-)">
                <div className="space-y-3 pt-3">
                  <Input
                    label="Points delta (positive or negative)"
                    type="number"
                    step="0.5"
                    value={adjustDelta}
                    onValueChange={setAdjustDelta}
                  />
                  <Textarea
                    label="Description"
                    minRows={2}
                    value={adjustDescription}
                    onValueChange={setAdjustDescription}
                  />
                  <div className="flex justify-end">
                    <Button
                      color="warning"
                      onPress={() => void handleAdjust()}
                      isLoading={submittingAdjust}
                    >
                      Apply adjustment
                    </Button>
                  </div>
                </div>
              </Tab>
            </Tabs>
          )}
        </CardBody>
      </Card>

      {/* Ledger */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Coins className="w-5 h-5 text-warning" />
          <h2 className="text-base font-semibold">Recent ledger entries</h2>
          <Chip size="sm" variant="flat" className="ml-auto">
            {items.length}
          </Chip>
        </CardHeader>
        <Divider />
        <CardBody className="p-0">
          {items.length === 0 ? (
            <div className="text-center py-12 text-sm text-default-500">
              No ledger entries yet.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-default-50">
                  <tr className="text-xs text-default-500 uppercase tracking-wide">
                    <th className="text-left px-4 py-3">Date</th>
                    <th className="text-left px-4 py-3">Member</th>
                    <th className="text-left px-4 py-3">Type</th>
                    <th className="text-left px-4 py-3 hidden md:table-cell">Description</th>
                    <th className="text-right px-4 py-3">Points</th>
                    <th className="text-right px-4 py-3 hidden md:table-cell">Balance</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((row) => (
                    <tr key={row.id} className="border-t border-default-200 hover:bg-default-50">
                      <td className="px-4 py-3 text-sm">
                        {new Date(row.created_at).toLocaleDateString()}
                      </td>
                      <td className="px-4 py-3 text-sm">
                        {row.user_name || `#${row.user_id}`}
                      </td>
                      <td className="px-4 py-3 text-sm">
                        <Chip size="sm" variant="flat">
                          {row.type}
                        </Chip>
                      </td>
                      <td className="px-4 py-3 text-sm text-default-600 hidden md:table-cell">
                        {row.description || '—'}
                      </td>
                      <td
                        className={`px-4 py-3 text-sm text-right tabular-nums ${
                          row.direction === 'in' ? 'text-success' : 'text-danger'
                        }`}
                      >
                        {row.direction === 'in' ? '+' : '−'}
                        {row.points.toFixed(2)}
                      </td>
                      <td className="px-4 py-3 text-sm text-right tabular-nums hidden md:table-cell text-default-500">
                        {row.balance_after.toFixed(2)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
