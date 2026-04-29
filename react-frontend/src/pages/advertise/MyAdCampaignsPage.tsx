// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MyAdCampaignsPage — Member-facing self-serve portal for managing ad campaigns.
 *
 * Allows local businesses / Gemeinden to create and track their own ad
 * campaigns against the feed and discovery surfaces.
 *
 * Route: /advertise/campaigns
 * Gate: feature "local_advertising"
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Chip,
  Input,
  Select,
  SelectItem,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
  Spinner,
} from '@heroui/react';
import { useTranslation } from 'react-i18next';
import Megaphone from 'lucide-react/icons/megaphone';
import Plus from 'lucide-react/icons/plus';
import BarChart3 from 'lucide-react/icons/chart-column';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import { GlassCard } from '@/components/ui';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks/usePageTitle';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type CampaignType = 'feed' | 'discovery';
type CampaignStatus = 'draft' | 'pending' | 'active' | 'paused' | 'ended' | 'rejected';

interface AdCampaign {
  id: number;
  name: string;
  type: CampaignType;
  status: CampaignStatus;
  start_date: string;
  end_date: string;
  budget_cents: number;
  targeting_radius_km?: number | null;
  created_at: string;
}

interface AdCampaignStats {
  impressions: number;
  clicks: number;
  ctr: number;
}

interface CreateCampaignForm {
  name: string;
  type: CampaignType;
  start_date: string;
  end_date: string;
  budget_cents: string; // string for input, converted on submit
  targeting_radius_km: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Status chip color map
// ─────────────────────────────────────────────────────────────────────────────

const STATUS_COLOR: Record<CampaignStatus, 'default' | 'primary' | 'success' | 'warning' | 'danger'> = {
  draft: 'default',
  pending: 'warning',
  active: 'success',
  paused: 'warning',
  ended: 'default',
  rejected: 'danger',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function MyAdCampaignsPage() {
  const { t } = useTranslation('common');
  usePageTitle(t('advertise.page_title'));
  const { isAuthenticated } = useAuth();
  const { hasFeature } = useTenant();
  const toast = useToast();

  const createModal = useDisclosure();

  const [campaigns, setCampaigns] = useState<AdCampaign[]>([]);
  const [stats, setStats] = useState<Record<number, AdCampaignStats>>({});
  const [isLoading, setIsLoading] = useState(true);
  const [isCreating, setIsCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [form, setForm] = useState<CreateCampaignForm>({
    name: '',
    type: 'feed',
    start_date: '',
    end_date: '',
    budget_cents: '',
    targeting_radius_km: '',
  });

  const fetchCampaigns = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const res = await api.get<{ data: AdCampaign[] } | AdCampaign[]>('/v2/me/ad-campaigns');
      if (res.success && res.data) {
        const list = Array.isArray(res.data)
          ? res.data
          : 'data' in res.data
          ? res.data.data
          : [];
        setCampaigns(list);
      }
    } catch (err) {
      logError('MyAdCampaignsPage.fetchCampaigns', err);
      setError(t('advertise.load_error'));
    } finally {
      setIsLoading(false);
    }
  }, [t]);

  const fetchStats = useCallback(async (id: number) => {
    try {
      const res = await api.get<AdCampaignStats>(`/v2/me/ad-campaigns/${id}/stats`);
      if (res.success && res.data) {
        setStats((prev) => ({ ...prev, [id]: res.data as AdCampaignStats }));
      }
    } catch {
      // Stats are non-critical — silently ignore
    }
  }, []);

  useEffect(() => {
    if (!isAuthenticated || !hasFeature('local_advertising')) return;
    fetchCampaigns();
  }, [isAuthenticated, hasFeature, fetchCampaigns]);

  // Fetch stats for active campaigns once the list is loaded
  useEffect(() => {
    for (const c of campaigns) {
      if (c.status === 'active') {
        fetchStats(c.id);
      }
    }
  }, [campaigns, fetchStats]);

  const handleCreate = async () => {
    if (!form.name.trim() || !form.start_date || !form.end_date || !form.budget_cents) {
      toast.showToast(t('advertise.validation_required'), 'error');
      return;
    }
    setIsCreating(true);
    try {
      const payload: Record<string, unknown> = {
        name: form.name.trim(),
        type: form.type,
        start_date: form.start_date,
        end_date: form.end_date,
        budget_cents: Math.round(parseFloat(form.budget_cents) * 100),
      };
      if (form.targeting_radius_km) {
        payload.targeting_radius_km = parseFloat(form.targeting_radius_km);
      }
      const res = await api.post<AdCampaign>('/v2/me/ad-campaigns', payload);
      if (res.success) {
        toast.showToast(t('advertise.created_success'), 'success');
        createModal.onClose();
        setForm({ name: '', type: 'feed', start_date: '', end_date: '', budget_cents: '', targeting_radius_km: '' });
        fetchCampaigns();
      } else {
        toast.showToast(t('advertise.create_error'), 'error');
      }
    } catch (err) {
      logError('MyAdCampaignsPage.handleCreate', err);
      toast.showToast(t('advertise.create_error'), 'error');
    } finally {
      setIsCreating(false);
    }
  };

  const formatBudget = (cents: number) =>
    `€${(cents / 100).toFixed(2)}`;

  const formatCtr = (ctr: number) =>
    `${(ctr * 100).toFixed(2)}%`;

  if (!hasFeature('local_advertising')) {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-center">
        <Megaphone size={48} className="mb-4 text-default-300" aria-hidden="true" />
        <p className="text-default-500">{t('advertise.feature_disabled')}</p>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto px-4 py-8 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Megaphone size={28} className="text-primary" aria-hidden="true" />
          <div>
            <h1 className="text-2xl font-bold text-theme-primary">{t('advertise.page_title')}</h1>
            <p className="text-sm text-theme-subtle">{t('advertise.page_subtitle')}</p>
          </div>
        </div>
        <Button
          color="primary"
          startContent={<Plus size={16} aria-hidden="true" />}
          onPress={createModal.onOpen}
        >
          {t('advertise.create_campaign')}
        </Button>
      </div>

      {/* Campaign list */}
      <GlassCard className="overflow-hidden">
        {isLoading ? (
          <div className="flex justify-center py-12">
            <Spinner />
          </div>
        ) : error ? (
          <div className="flex flex-col items-center py-12 text-center gap-4">
            <AlertTriangle size={40} className="text-warning" aria-hidden="true" />
            <p className="text-default-500">{error}</p>
            <Button variant="flat" onPress={fetchCampaigns}>{t('advertise.retry')}</Button>
          </div>
        ) : campaigns.length === 0 ? (
          <div className="flex flex-col items-center py-12 text-center gap-3">
            <Megaphone size={40} className="text-default-300" aria-hidden="true" />
            <p className="text-default-500">{t('advertise.empty')}</p>
            <Button color="primary" onPress={createModal.onOpen}>
              {t('advertise.create_first')}
            </Button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-divider text-left text-default-400 text-xs uppercase tracking-wider">
                  <th className="px-4 py-3">{t('advertise.col_name')}</th>
                  <th className="px-4 py-3">{t('advertise.col_type')}</th>
                  <th className="px-4 py-3">{t('advertise.col_status')}</th>
                  <th className="px-4 py-3">{t('advertise.col_budget')}</th>
                  <th className="px-4 py-3">{t('advertise.col_dates')}</th>
                  <th className="px-4 py-3">{t('advertise.col_impressions')}</th>
                  <th className="px-4 py-3">{t('advertise.col_clicks')}</th>
                  <th className="px-4 py-3">{t('advertise.col_ctr')}</th>
                </tr>
              </thead>
              <tbody>
                {campaigns.map((campaign) => {
                  const s = stats[campaign.id];
                  return (
                    <tr key={campaign.id} className="border-b border-divider/50 hover:bg-default-50 transition-colors">
                      <td className="px-4 py-3 font-medium text-theme-primary">{campaign.name}</td>
                      <td className="px-4 py-3 capitalize text-default-500">
                        {t(`advertise.type_${campaign.type}`)}
                      </td>
                      <td className="px-4 py-3">
                        <Chip
                          size="sm"
                          color={STATUS_COLOR[campaign.status] ?? 'default'}
                          variant="flat"
                        >
                          {t(`advertise.status_${campaign.status}`)}
                        </Chip>
                      </td>
                      <td className="px-4 py-3 text-default-600">{formatBudget(campaign.budget_cents)}</td>
                      <td className="px-4 py-3 text-default-500 whitespace-nowrap">
                        {campaign.start_date} → {campaign.end_date}
                      </td>
                      <td className="px-4 py-3 text-default-600">{s ? s.impressions.toLocaleString() : '—'}</td>
                      <td className="px-4 py-3 text-default-600">{s ? s.clicks.toLocaleString() : '—'}</td>
                      <td className="px-4 py-3 text-default-600">{s ? formatCtr(s.ctr) : '—'}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </GlassCard>

      {/* Create Campaign Modal */}
      <Modal isOpen={createModal.isOpen} onClose={createModal.onClose} size="lg">
        <ModalContent>
          <ModalHeader>{t('advertise.create_campaign')}</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                label={t('advertise.field_name')}
                placeholder={t('advertise.field_name_placeholder')}
                value={form.name}
                onValueChange={(v) => setForm((p) => ({ ...p, name: v }))}
                variant="bordered"
                isRequired
              />

              <Select
                label={t('advertise.field_type')}
                selectedKeys={[form.type]}
                onSelectionChange={(keys) => {
                  const v = Array.from(keys)[0] as CampaignType;
                  if (v) setForm((p) => ({ ...p, type: v }));
                }}
                variant="bordered"
              >
                <SelectItem key="feed">{t('advertise.type_feed')}</SelectItem>
                <SelectItem key="discovery">{t('advertise.type_discovery')}</SelectItem>
              </Select>

              <div className="grid grid-cols-2 gap-3">
                <Input
                  label={t('advertise.field_start_date')}
                  type="date"
                  value={form.start_date}
                  onValueChange={(v) => setForm((p) => ({ ...p, start_date: v }))}
                  variant="bordered"
                  isRequired
                />
                <Input
                  label={t('advertise.field_end_date')}
                  type="date"
                  value={form.end_date}
                  onValueChange={(v) => setForm((p) => ({ ...p, end_date: v }))}
                  variant="bordered"
                  isRequired
                />
              </div>

              <Input
                label={t('advertise.field_budget')}
                placeholder="50.00"
                type="number"
                min="1"
                step="0.01"
                startContent={<span className="text-default-400 text-sm">€</span>}
                value={form.budget_cents}
                onValueChange={(v) => setForm((p) => ({ ...p, budget_cents: v }))}
                variant="bordered"
                isRequired
                description={t('advertise.field_budget_desc')}
              />

              <Input
                label={t('advertise.field_radius')}
                placeholder="25"
                type="number"
                min="1"
                endContent={<span className="text-default-400 text-sm">km</span>}
                value={form.targeting_radius_km}
                onValueChange={(v) => setForm((p) => ({ ...p, targeting_radius_km: v }))}
                variant="bordered"
                description={t('advertise.field_radius_desc')}
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={createModal.onClose} isDisabled={isCreating}>
              {t('cancel')}
            </Button>
            <Button color="primary" onPress={handleCreate} isLoading={isCreating}>
              {t('advertise.submit_create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default MyAdCampaignsPage;
