// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LoyaltyAdminPage — Admin console for the time-credit ↔ marketplace
 * loyalty bridge.
 *
 * - Aggregate redemption stats (count, hours, CHF discount)
 * - Recent redemption ledger (last 50)
 * - Per-seller loyalty configuration (pick a member, toggle accept,
 *   set CHF/hour and max discount %)
 *
 * Admin English only — no t() calls.
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
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Spinner,
  Switch,
  Textarea,
} from '@heroui/react';
import Coins from 'lucide-react/icons/coins';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Save from 'lucide-react/icons/save';
import Wallet from 'lucide-react/icons/wallet';
import Store from 'lucide-react/icons/store';
import Undo2 from 'lucide-react/icons/undo-2';
import { usePageTitle } from '@/hooks';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { canManageCaring } from '@/caring/access';
import { MemberSearchPicker, PageHeader, StatCard, type MemberSearchMember } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface Redemption {
  id: number;
  credits_used: number;
  exchange_rate_chf: number;
  discount_chf: number;
  order_total_chf: number;
  status: 'pending' | 'applied' | 'reversed';
  redeemed_at: string;
  member_id: number | null;
  member_name: string;
  merchant_id: number | null;
  merchant_name: string;
  marketplace_listing_id: number | null;
  listing_title: string | null;
}

interface RedemptionsResponse {
  stats: {
    total_redemptions: number;
    total_credits: number;
    total_discount_chf: number;
  };
  redemptions: Redemption[];
}

interface SellerSettings {
  seller_user_id: number;
  accepts_time_credits: boolean;
  loyalty_chf_per_hour: number;
  loyalty_max_discount_pct: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function LoyaltyAdminPage() {
  const toast = useToast();
  const { user } = useAuth();
  const { t } = useTranslation('caring_community');
  const canManage = canManageCaring(user);
  usePageTitle(t('admin.loyalty.title'));

  const [data, setData] = useState<RedemptionsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  // Seller settings editor state
  const [sellerQuery, setSellerQuery] = useState('');
  const [selectedSeller, setSelectedSeller] = useState<MemberSearchMember | null>(null);
  const [settings, setSettings] = useState<SellerSettings | null>(null);
  const [settingsLoading, setSettingsLoading] = useState(false);
  const [savingSettings, setSavingSettings] = useState(false);

  // Reversal modal state
  const [reverseTarget, setReverseTarget] = useState<Redemption | null>(null);
  const [reverseReason, setReverseReason] = useState('');
  const [reversing, setReversing] = useState(false);

  const loadRedemptions = useCallback(async () => {
    try {
      setRefreshing(true);
      const res = await api.get<RedemptionsResponse>(
        '/v2/admin/caring-community/loyalty/redemptions?limit=50',
      );
      if (res.success && res.data) {
        setData(res.data);
      } else {
        toast.error(res.error || t('admin.loyalty.errors.load_redemptions'));
      }
    } catch (err) {
      logError('LoyaltyAdminPage: load redemptions failed', err);
      toast.error(t('admin.loyalty.errors.load_redemptions'));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [t, toast]);

  useEffect(() => {
    void loadRedemptions();
  }, [loadRedemptions]);

  const loadSettingsFor = useCallback(
    async (sellerId: number) => {
      try {
        setSettingsLoading(true);
        const res = await api.get<SellerSettings>(
          `/v2/admin/caring-community/loyalty/seller-settings/${sellerId}`,
        );
        if (res.success && res.data) {
          setSettings(res.data);
        } else {
          toast.error(res.error || t('admin.loyalty.errors.load_settings'));
        }
      } catch (err) {
        logError('LoyaltyAdminPage: load settings failed', err);
        toast.error(t('admin.loyalty.errors.load_settings'));
      } finally {
        setSettingsLoading(false);
      }
    },
    [t, toast],
  );

  const handleSelectSeller = useCallback(
    (member: MemberSearchMember | null) => {
      setSelectedSeller(member);
      if (member) {
        void loadSettingsFor(member.id);
      } else {
        setSettings(null);
      }
    },
    [loadSettingsFor],
  );

  const handleSaveSettings = useCallback(async () => {
    if (!settings || !selectedSeller) return;
    if (settings.loyalty_chf_per_hour <= 0) {
      toast.error(t('admin.loyalty.errors.rate_positive'));
      return;
    }
    if (settings.loyalty_max_discount_pct < 0 || settings.loyalty_max_discount_pct > 100) {
      toast.error(t('admin.loyalty.errors.discount_range'));
      return;
    }

    try {
      setSavingSettings(true);
      const res = await api.put<SellerSettings>('/v2/admin/caring-community/loyalty/seller-settings', {
        seller_user_id: selectedSeller.id,
        accepts_time_credits: settings.accepts_time_credits,
        loyalty_chf_per_hour: settings.loyalty_chf_per_hour,
        loyalty_max_discount_pct: settings.loyalty_max_discount_pct,
      });
      if (res.success && res.data) {
        setSettings(res.data);
        toast.success(t('admin.loyalty.messages.settings_saved'));
      } else {
        toast.error(res.error || t('admin.loyalty.errors.save_settings'));
      }
    } catch (err) {
      logError('LoyaltyAdminPage: save settings failed', err);
      toast.error(t('admin.loyalty.errors.save_settings'));
    } finally {
      setSavingSettings(false);
    }
  }, [settings, selectedSeller, t, toast]);

  const openReverseModal = useCallback((row: Redemption) => {
    setReverseTarget(row);
    setReverseReason('');
  }, []);

  const closeReverseModal = useCallback(() => {
    if (reversing) return;
    setReverseTarget(null);
    setReverseReason('');
  }, [reversing]);

  const handleConfirmReverse = useCallback(async () => {
    if (!reverseTarget) return;
    setReversing(true);
    try {
      const res = await api.post<{
        redemption_id: number;
        credits_restored: number;
        member_new_balance: number;
      }>(`/v2/admin/caring-community/loyalty/redemptions/${reverseTarget.id}/reverse`, {
        reason: reverseReason.trim() || undefined,
      });
      if (res.success && res.data) {
        toast.success(
          t('admin.loyalty.messages.reversed', { hours: res.data.credits_restored.toFixed(2) }),
        );
        setReverseTarget(null);
        setReverseReason('');
        await loadRedemptions();
      } else {
        toast.error(res.error || t('admin.loyalty.errors.reverse'));
      }
    } catch (err) {
      logError('LoyaltyAdminPage: reverse failed', err);
      toast.error(t('admin.loyalty.errors.reverse'));
    } finally {
      setReversing(false);
    }
  }, [reverseTarget, reverseReason, t, toast, loadRedemptions]);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  const stats = data?.stats ?? { total_redemptions: 0, total_credits: 0, total_discount_chf: 0 };
  const redemptions = data?.redemptions ?? [];

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('admin.loyalty.title')}
        description={t('admin.loyalty.description')}
        actions={
          <Button
            size="sm"
            variant="bordered"
            startContent={<RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />}
            onPress={() => void loadRedemptions()}
            isDisabled={refreshing}
          >
            {t('admin.loyalty.refresh')}
          </Button>
        }
      />

      {/* Intro card */}
      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">{t('admin.loyalty.about.title')}</p>
              <p className="text-default-600">
                {t('admin.loyalty.about.body')}
              </p>
              <div className="space-y-0.5 pt-1 text-default-500">
                <p><strong>{t('admin.loyalty.about.rate_label')}</strong> {t('admin.loyalty.about.rate_body')}</p>
                <p><strong>{t('admin.loyalty.about.discount_label')}</strong> {t('admin.loyalty.about.discount_body')}</p>
              </div>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* ── Stats ─────────────────────────────────────────────────────────── */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <StatCard
          icon={Coins}
          label={t('admin.loyalty.stats.total_redemptions')}
          value={stats.total_redemptions.toLocaleString()}
          color="warning"
        />
        <StatCard
          icon={Wallet}
          label={t('admin.loyalty.stats.hours_redeemed')}
          value={stats.total_credits.toFixed(2)}
          color="primary"
        />
        <StatCard
          icon={Store}
          label={t('admin.loyalty.stats.total_chf_discount')}
          value={`CHF ${stats.total_discount_chf.toFixed(2)}`}
          color="success"
        />
      </div>

      {/* ── Seller settings editor ────────────────────────────────────────── */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Store className="w-5 h-5 text-primary" />
          <h2 className="text-base font-semibold">{t('admin.loyalty.settings.title')}</h2>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <p className="text-sm text-default-600">
            {t('admin.loyalty.settings.description')}
          </p>

          <MemberSearchPicker
            label={t('admin.loyalty.settings.seller_label')}
            placeholder={t('admin.loyalty.settings.seller_placeholder')}
            value={sellerQuery}
            onValueChange={setSellerQuery}
            selectedMember={selectedSeller}
            onSelectedMemberChange={handleSelectSeller}
            noResultsText={t('admin.loyalty.settings.no_matching_members')}
            clearText={t('admin.loyalty.settings.clear')}
          />

          {selectedSeller && (
            <>
              {settingsLoading && (
                <div className="flex items-center justify-center py-8">
                  <Spinner size="md" />
                </div>
              )}

              {settings && !settingsLoading && (
                <div className="space-y-4 border-t border-default-200 pt-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium">{t('admin.loyalty.settings.accept_time_credits')}</p>
                      <p className="text-xs text-default-500">
                        {t('admin.loyalty.settings.accept_time_credits_hint')}
                      </p>
                    </div>
                    <Switch
                      isSelected={settings.accepts_time_credits}
                      onValueChange={(v) =>
                        setSettings({ ...settings, accepts_time_credits: v })
                      }
                      color="success"
                      isDisabled={!canManage}
                    />
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Input
                      type="number"
                      label={t('admin.loyalty.settings.exchange_rate')}
                      description={t('admin.loyalty.settings.exchange_rate_description')}
                      value={String(settings.loyalty_chf_per_hour)}
                      onValueChange={(v) =>
                        setSettings({
                          ...settings,
                          loyalty_chf_per_hour: parseFloat(v) || 0,
                        })
                      }
                      min="0"
                      step="0.5"
                      startContent={<span className="text-default-400 text-xs">CHF</span>}
                      endContent={<span className="text-default-400 text-xs">/ h</span>}
                      isDisabled={!settings.accepts_time_credits || !canManage}
                    />
                    <Input
                      type="number"
                      label={t('admin.loyalty.settings.maximum_discount')}
                      description={t('admin.loyalty.settings.maximum_discount_description')}
                      value={String(settings.loyalty_max_discount_pct)}
                      onValueChange={(v) =>
                        setSettings({
                          ...settings,
                          loyalty_max_discount_pct: parseInt(v, 10) || 0,
                        })
                      }
                      min="0"
                      max="100"
                      step="5"
                      endContent={<span className="text-default-400 text-xs">%</span>}
                      isDisabled={!settings.accepts_time_credits || !canManage}
                    />
                  </div>

                  {!canManage && (
                    <p className="text-sm text-default-500">{t('admin.loyalty.settings.view_only')}</p>
                  )}

                  {canManage && (
                    <div className="flex justify-end">
                      <Button
                        color="primary"
                        startContent={<Save className="w-4 h-4" />}
                        onPress={() => void handleSaveSettings()}
                        isLoading={savingSettings}
                      >
                        {t('admin.loyalty.settings.save')}
                      </Button>
                    </div>
                  )}
                </div>
              )}
            </>
          )}
        </CardBody>
      </Card>

      {/* ── Redemption ledger ─────────────────────────────────────────────── */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Coins className="w-5 h-5 text-warning" />
          <h2 className="text-base font-semibold">{t('admin.loyalty.ledger.title')}</h2>
          <Chip size="sm" variant="flat" className="ml-auto">
            {redemptions.length}
          </Chip>
        </CardHeader>
        <Divider />
        <CardBody className="p-0">
          {redemptions.length === 0 ? (
            <div className="text-center py-12 text-sm text-default-500">
              {t('admin.loyalty.ledger.empty')}
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-default-50">
                  <tr className="text-xs text-default-500 uppercase tracking-wide">
                    <th className="text-left px-4 py-3">{t('admin.loyalty.ledger.date')}</th>
                    <th className="text-left px-4 py-3">{t('admin.loyalty.ledger.member')}</th>
                    <th className="text-left px-4 py-3">{t('admin.loyalty.ledger.merchant')}</th>
                    <th className="text-left px-4 py-3 hidden md:table-cell">{t('admin.loyalty.ledger.item')}</th>
                    <th className="text-right px-4 py-3">{t('admin.loyalty.ledger.hours')}</th>
                    <th className="text-right px-4 py-3">{t('admin.loyalty.ledger.rate')}</th>
                    <th className="text-right px-4 py-3">{t('admin.loyalty.ledger.discount')}</th>
                    <th className="text-left px-4 py-3">{t('admin.loyalty.ledger.status')}</th>
                    <th className="text-right px-4 py-3">{t('admin.loyalty.ledger.actions')}</th>
                  </tr>
                </thead>
                <tbody>
                  {redemptions.map((row) => (
                    <tr key={row.id} className="border-t border-default-200 hover:bg-default-50">
                      <td className="px-4 py-3 text-sm">
                        {new Date(row.redeemed_at).toLocaleDateString()}
                      </td>
                      <td className="px-4 py-3 text-sm">{row.member_name || '—'}</td>
                      <td className="px-4 py-3 text-sm">{row.merchant_name || '—'}</td>
                      <td className="px-4 py-3 text-sm hidden md:table-cell">
                        {row.listing_title || '—'}
                      </td>
                      <td className="px-4 py-3 text-sm text-right tabular-nums">
                        {row.credits_used.toFixed(2)}
                      </td>
                      <td className="px-4 py-3 text-sm text-right tabular-nums text-default-500">
                        {row.exchange_rate_chf.toFixed(2)}
                      </td>
                      <td className="px-4 py-3 text-sm text-right tabular-nums">
                        <Chip variant="flat" color="success" size="sm">
                          CHF {row.discount_chf.toFixed(2)}
                        </Chip>
                      </td>
                      <td className="px-4 py-3 text-sm">
                        <Chip
                          variant="flat"
                          size="sm"
                          color={
                            row.status === 'applied'
                              ? 'success'
                              : row.status === 'reversed'
                              ? 'danger'
                              : 'warning'
                          }
                        >
                          {row.status}
                        </Chip>
                      </td>
                      <td className="px-4 py-3 text-sm text-right">
                        {canManage && row.status === 'applied' ? (
                          <Button
                            size="sm"
                            color="danger"
                            variant="flat"
                            startContent={<Undo2 className="w-4 h-4" />}
                            onPress={() => openReverseModal(row)}
                          >
                            {t('admin.loyalty.ledger.reverse')}
                          </Button>
                        ) : (
                          <span className="text-default-400">—</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>

      {/* ── Reversal confirmation modal ─────────────────────────────────── */}
      <Modal isOpen={reverseTarget !== null} onClose={closeReverseModal} size="md">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Undo2 className="w-5 h-5 text-danger" />
            <span>{t('admin.loyalty.reverse_modal.title')}</span>
          </ModalHeader>
          <ModalBody className="gap-4">
            {reverseTarget && (
              <>
                <p className="text-sm text-default-700">
                  {t('admin.loyalty.reverse_modal.body', {
                    hours: reverseTarget.credits_used.toFixed(2),
                    member: reverseTarget.member_name || t('admin.loyalty.reverse_modal.member_fallback'),
                  })}
                </p>
                <div className="rounded-md bg-default-100 px-3 py-2 text-xs text-default-600">
                  <div>Merchant: {reverseTarget.merchant_name || '—'}</div>
                  <div>
                    {t('admin.loyalty.reverse_modal.discount', {
                      discount: reverseTarget.discount_chf.toFixed(2),
                      total: reverseTarget.order_total_chf.toFixed(2),
                    })}
                  </div>
                  <div>
                    {t('admin.loyalty.reverse_modal.redeemed', { date: new Date(reverseTarget.redeemed_at).toLocaleString() })}
                  </div>
                </div>
                <Textarea
                  label={t('admin.loyalty.reverse_modal.reason')}
                  placeholder={t('admin.loyalty.reverse_modal.reason_placeholder')}
                  value={reverseReason}
                  onValueChange={setReverseReason}
                  variant="bordered"
                  minRows={2}
                  maxRows={4}
                  maxLength={500}
                />
              </>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={closeReverseModal} isDisabled={reversing}>
              {t('admin.loyalty.reverse_modal.cancel')}
            </Button>
            <Button
              color="danger"
              startContent={!reversing ? <Undo2 className="w-4 h-4" /> : null}
              onPress={() => void handleConfirmReverse()}
              isLoading={reversing}
            >
              {t('admin.loyalty.reverse_modal.confirm')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
