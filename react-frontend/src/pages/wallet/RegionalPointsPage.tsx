// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * RegionalPointsPage — AG28 member-facing UI for the third currency.
 *
 * Shows balance, transaction history and a member-to-member transfer form.
 * Backed by /api/v2/caring-community/regional-points/{summary,history,transfer}.
 *
 * Disabled tenants see a friendly "not enabled here" message — backend
 * returns FEATURE_DISABLED in that case.
 */

import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Spinner,
  Textarea,
} from '@heroui/react';
import Coins from 'lucide-react/icons/coins';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Send from 'lucide-react/icons/send';
import ArrowDownCircle from 'lucide-react/icons/arrow-down-circle';
import ArrowUpCircle from 'lucide-react/icons/arrow-up-circle';
import { PageMeta } from '@/components/seo';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface SummaryConfig {
  enabled: boolean;
  label: string;
  symbol: string;
  member_transfers_enabled: boolean;
  marketplace_redemption_enabled: boolean;
  points_per_approved_hour: number;
}

interface SummaryResponse {
  enabled: boolean;
  config: SummaryConfig;
  account: {
    user_id: number;
    balance: number;
    lifetime_earned: number;
    lifetime_spent: number;
  };
}

interface PointTransaction {
  id: number;
  user_id: number;
  actor_user_id: number | null;
  type: string;
  direction: 'in' | 'out' | string;
  points: number;
  balance_after: number;
  description: string | null;
  created_at: string;
}

interface HistoryResponse {
  items: PointTransaction[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function RegionalPointsPage() {
  const { t } = useTranslation('common');
  const toast = useToast();
  usePageTitle(t('regional_points.title', 'Regional Points'));

  const [summary, setSummary] = useState<SummaryResponse | null>(null);
  const [history, setHistory] = useState<PointTransaction[] | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [unavailable, setUnavailable] = useState(false);

  // Transfer form
  const [recipientId, setRecipientId] = useState('');
  const [points, setPoints] = useState('');
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(async () => {
    setRefreshing(true);
    try {
      const [sumRes, histRes] = await Promise.all([
        api.get<SummaryResponse>('/v2/caring-community/regional-points/summary'),
        api.get<HistoryResponse>('/v2/caring-community/regional-points/history?limit=50'),
      ]);
      if (sumRes.success && sumRes.data) {
        setSummary(sumRes.data);
        setUnavailable(false);
      } else if (sumRes.error) {
        // Feature disabled or not configured.
        setUnavailable(true);
      }
      if (histRes.success && histRes.data) {
        setHistory(histRes.data.items || []);
      }
    } catch (err) {
      logError('RegionalPointsPage: load failed', err);
      toast.error(t('regional_points.errors.load_failed', 'Failed to load regional points'));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [toast, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleTransfer = useCallback(async () => {
    const recipient = parseInt(recipientId, 10);
    const amount = parseFloat(points);
    if (!recipient || recipient <= 0) {
      toast.error(t('regional_points.transfer.errors.invalid_recipient', 'Enter a valid recipient member ID'));
      return;
    }
    if (!amount || amount <= 0) {
      toast.error(t('regional_points.transfer.errors.invalid_amount', 'Enter a positive amount'));
      return;
    }
    setSubmitting(true);
    try {
      const res = await api.post('/v2/caring-community/regional-points/transfer', {
        recipient_user_id: recipient,
        points: amount,
        message: message.trim() || null,
      });
      if (res.success) {
        toast.success(t('regional_points.transfer.success', 'Transfer sent'));
        setRecipientId('');
        setPoints('');
        setMessage('');
        await load();
      } else {
        toast.error(res.error || t('regional_points.errors.transfer_failed', 'Transfer failed'));
      }
    } catch (err) {
      logError('RegionalPointsPage: transfer failed', err);
      toast.error(t('regional_points.errors.transfer_failed', 'Transfer failed'));
    } finally {
      setSubmitting(false);
    }
  }, [recipientId, points, message, toast, t, load]);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  if (unavailable || !summary) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-8">
        <PageMeta title={t('regional_points.title', 'Regional Points')} />
        <EmptyState
          title={t('regional_points.unavailable.title', 'Regional points are not enabled here')}
          description={t(
            'regional_points.unavailable.description',
            'This community has not turned on the regional points programme yet.',
          )}
        />
      </div>
    );
  }

  const cfg = summary.config;
  const account = summary.account;
  const symbol = cfg.symbol || 'pts';

  return (
    <div className="mx-auto max-w-5xl px-4 py-6 space-y-6">
      <PageMeta title={t('regional_points.title', 'Regional Points')} />

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold flex items-center gap-2">
            <Coins className="w-6 h-6 text-warning" />
            {cfg.label || t('regional_points.title', 'Regional Points')}
          </h1>
          <p className="text-sm text-default-500 mt-1">
            {t(
              'regional_points.subtitle',
              'A regional currency you can earn and spend with neighbours and local merchants.',
            )}
          </p>
        </div>
        <Button
          size="sm"
          variant="bordered"
          startContent={<RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />}
          onPress={() => void load()}
          isDisabled={refreshing}
        >
          {t('common.refresh', 'Refresh')}
        </Button>
      </div>

      {/* Balance */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardBody>
            <p className="text-xs uppercase text-default-500 tracking-wide">
              {t('regional_points.balance', 'Balance')}
            </p>
            <p className="text-3xl font-bold tabular-nums mt-1">
              {account.balance.toFixed(2)} <span className="text-base text-default-500">{symbol}</span>
            </p>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <p className="text-xs uppercase text-default-500 tracking-wide">
              {t('regional_points.lifetime_earned', 'Lifetime earned')}
            </p>
            <p className="text-3xl font-bold tabular-nums mt-1">
              {account.lifetime_earned.toFixed(2)} <span className="text-base text-default-500">{symbol}</span>
            </p>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <p className="text-xs uppercase text-default-500 tracking-wide">
              {t('regional_points.lifetime_spent', 'Lifetime spent')}
            </p>
            <p className="text-3xl font-bold tabular-nums mt-1">
              {account.lifetime_spent.toFixed(2)} <span className="text-base text-default-500">{symbol}</span>
            </p>
          </CardBody>
        </Card>
      </div>

      {/* Transfer */}
      {cfg.member_transfers_enabled && (
        <Card>
          <CardHeader className="flex items-center gap-2">
            <Send className="w-5 h-5 text-primary" />
            <h2 className="text-base font-semibold">
              {t('regional_points.transfer.title', 'Send points to another member')}
            </h2>
          </CardHeader>
          <Divider />
          <CardBody className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Input
                label={t('regional_points.transfer.recipient_id', 'Recipient member ID')}
                placeholder="e.g. 123"
                type="number"
                value={recipientId}
                onValueChange={setRecipientId}
              />
              <Input
                label={t('regional_points.transfer.amount', 'Amount')}
                placeholder="0.00"
                type="number"
                step="0.01"
                min="0"
                value={points}
                onValueChange={setPoints}
                endContent={<span className="text-default-400 text-xs">{symbol}</span>}
              />
            </div>
            <Textarea
              label={t('regional_points.transfer.message', 'Message (optional)')}
              value={message}
              onValueChange={setMessage}
              minRows={2}
              maxRows={4}
            />
            <div className="flex justify-end">
              <Button
                color="primary"
                startContent={<Send className="w-4 h-4" />}
                onPress={() => void handleTransfer()}
                isLoading={submitting}
              >
                {t('regional_points.transfer.submit', 'Send transfer')}
              </Button>
            </div>
          </CardBody>
        </Card>
      )}

      {/* History */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Coins className="w-5 h-5 text-warning" />
          <h2 className="text-base font-semibold">
            {t('regional_points.history.title', 'Recent activity')}
          </h2>
          <Chip size="sm" variant="flat" className="ml-auto">
            {history?.length ?? 0}
          </Chip>
        </CardHeader>
        <Divider />
        <CardBody className="p-0">
          {!history || history.length === 0 ? (
            <div className="text-center py-12 text-sm text-default-500">
              {t('regional_points.history.empty', 'No transactions yet.')}
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-default-50">
                  <tr className="text-xs text-default-500 uppercase tracking-wide">
                    <th className="text-left px-4 py-3">{t('regional_points.history.date', 'Date')}</th>
                    <th className="text-left px-4 py-3">{t('regional_points.history.type', 'Type')}</th>
                    <th className="text-left px-4 py-3">{t('regional_points.history.description', 'Description')}</th>
                    <th className="text-right px-4 py-3">{t('regional_points.history.amount', 'Amount')}</th>
                    <th className="text-right px-4 py-3 hidden md:table-cell">{t('regional_points.history.balance_after', 'Balance')}</th>
                  </tr>
                </thead>
                <tbody>
                  {history.map((row) => {
                    const inbound = row.direction === 'in';
                    return (
                      <tr key={row.id} className="border-t border-default-200 hover:bg-default-50">
                        <td className="px-4 py-3 text-sm">
                          {new Date(row.created_at).toLocaleDateString()}
                        </td>
                        <td className="px-4 py-3 text-sm">
                          <span className="inline-flex items-center gap-1">
                            {inbound ? (
                              <ArrowDownCircle className="w-4 h-4 text-success" />
                            ) : (
                              <ArrowUpCircle className="w-4 h-4 text-danger" />
                            )}
                            {row.type}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-sm text-default-600">{row.description || '—'}</td>
                        <td className={`px-4 py-3 text-sm text-right tabular-nums ${inbound ? 'text-success' : 'text-danger'}`}>
                          {inbound ? '+' : '−'}
                          {row.points.toFixed(2)} {symbol}
                        </td>
                        <td className="px-4 py-3 text-sm text-right tabular-nums hidden md:table-cell text-default-500">
                          {row.balance_after.toFixed(2)}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
