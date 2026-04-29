// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MyPushCampaignsPage — Member-facing self-serve portal for managing push campaigns.
 *
 * Allows local businesses / Gemeinden to create and send targeted push
 * notification campaigns to nearby opted-in members.
 *
 * Route: /advertise/push-campaigns
 * Gate: feature "local_advertising"
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Chip,
  Input,
  Select,
  SelectItem,
  Textarea,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
  Spinner,
} from '@heroui/react';
import { useTranslation } from 'react-i18next';
import BellRing from 'lucide-react/icons/bell-ring';
import Plus from 'lucide-react/icons/plus';
import Users from 'lucide-react/icons/users';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import { GlassCard } from '@/components/ui';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks/usePageTitle';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

type TrustTier = 'any' | 'member' | 'trusted' | 'verified';
type CampaignStatus = 'draft' | 'scheduled' | 'sent' | 'cancelled' | 'failed';

interface PushCampaign {
  id: number;
  name: string;
  title: string;
  body: string;
  status: CampaignStatus;
  schedule_at: string | null;
  audience_radius_km: number;
  audience_min_trust_tier: TrustTier;
  created_at: string;
}

interface AudienceEstimate {
  estimated_reach: number;
}

interface CreatePushCampaignForm {
  name: string;
  title: string;
  body: string;
  schedule_at: string;
  audience_radius_km: string;
  audience_min_trust_tier: TrustTier;
}

// ─────────────────────────────────────────────────────────────────────────────
// Status chip color map
// ─────────────────────────────────────────────────────────────────────────────

const STATUS_COLOR: Record<CampaignStatus, 'default' | 'primary' | 'success' | 'warning' | 'danger'> = {
  draft: 'default',
  scheduled: 'primary',
  sent: 'success',
  cancelled: 'default',
  failed: 'danger',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function MyPushCampaignsPage() {
  const { t } = useTranslation('common');
  usePageTitle(t('push_campaign.page_title'));
  const { isAuthenticated } = useAuth();
  const { hasFeature } = useTenant();
  const toast = useToast();

  const createModal = useDisclosure();

  const [campaigns, setCampaigns] = useState<PushCampaign[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isCreating, setIsCreating] = useState(false);
  const [isEstimating, setIsEstimating] = useState(false);
  const [audienceEstimate, setAudienceEstimate] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const defaultForm: CreatePushCampaignForm = {
    name: '',
    title: '',
    body: '',
    schedule_at: '',
    audience_radius_km: '',
    audience_min_trust_tier: 'any',
  };
  const [form, setForm] = useState<CreatePushCampaignForm>(defaultForm);

  const fetchCampaigns = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const res = await api.get<{ data: PushCampaign[] } | PushCampaign[]>('/v2/me/push-campaigns');
      if (res.success && res.data) {
        const list = Array.isArray(res.data)
          ? res.data
          : 'data' in res.data
          ? res.data.data
          : [];
        setCampaigns(list);
      }
    } catch (err) {
      logError('MyPushCampaignsPage.fetchCampaigns', err);
      setError(t('push_campaign.load_error'));
    } finally {
      setIsLoading(false);
    }
  }, [t]);

  useEffect(() => {
    if (!isAuthenticated || !hasFeature('local_advertising')) return;
    fetchCampaigns();
  }, [isAuthenticated, hasFeature, fetchCampaigns]);

  const handleEstimateAudience = async () => {
    setIsEstimating(true);
    setAudienceEstimate(null);
    try {
      const payload: Record<string, unknown> = {
        audience_min_trust_tier: form.audience_min_trust_tier,
      };
      if (form.audience_radius_km) {
        payload.audience_radius_km = parseFloat(form.audience_radius_km);
      }
      const res = await api.post<AudienceEstimate>('/v2/me/push-campaigns/estimate-audience', payload);
      if (res.success && res.data) {
        const estimate = (res.data as AudienceEstimate).estimated_reach;
        setAudienceEstimate(estimate);
      }
    } catch (err) {
      logError('MyPushCampaignsPage.handleEstimateAudience', err);
      toast.showToast(t('push_campaign.estimate_error'), 'error');
    } finally {
      setIsEstimating(false);
    }
  };

  const handleCreate = async () => {
    if (!form.name.trim() || !form.title.trim() || !form.body.trim()) {
      toast.showToast(t('push_campaign.validation_required'), 'error');
      return;
    }
    setIsCreating(true);
    try {
      const payload: Record<string, unknown> = {
        name: form.name.trim(),
        title: form.title.trim(),
        body: form.body.trim(),
        audience_min_trust_tier: form.audience_min_trust_tier,
      };
      if (form.schedule_at) payload.schedule_at = form.schedule_at;
      if (form.audience_radius_km) {
        payload.audience_radius_km = parseFloat(form.audience_radius_km);
      }
      const res = await api.post<PushCampaign>('/v2/me/push-campaigns', payload);
      if (res.success) {
        toast.showToast(t('push_campaign.created_success'), 'success');
        createModal.onClose();
        setForm(defaultForm);
        setAudienceEstimate(null);
        fetchCampaigns();
      } else {
        toast.showToast(t('push_campaign.create_error'), 'error');
      }
    } catch (err) {
      logError('MyPushCampaignsPage.handleCreate', err);
      toast.showToast(t('push_campaign.create_error'), 'error');
    } finally {
      setIsCreating(false);
    }
  };

  const handleModalClose = () => {
    createModal.onClose();
    setForm(defaultForm);
    setAudienceEstimate(null);
  };

  if (!hasFeature('local_advertising')) {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-center">
        <BellRing size={48} className="mb-4 text-default-300" aria-hidden="true" />
        <p className="text-default-500">{t('push_campaign.feature_disabled')}</p>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto px-4 py-8 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <BellRing size={28} className="text-primary" aria-hidden="true" />
          <div>
            <h1 className="text-2xl font-bold text-theme-primary">{t('push_campaign.page_title')}</h1>
            <p className="text-sm text-theme-subtle">{t('push_campaign.page_subtitle')}</p>
          </div>
        </div>
        <Button
          color="primary"
          startContent={<Plus size={16} aria-hidden="true" />}
          onPress={createModal.onOpen}
        >
          {t('push_campaign.create_campaign')}
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
            <Button variant="flat" onPress={fetchCampaigns}>{t('push_campaign.retry')}</Button>
          </div>
        ) : campaigns.length === 0 ? (
          <div className="flex flex-col items-center py-12 text-center gap-3">
            <BellRing size={40} className="text-default-300" aria-hidden="true" />
            <p className="text-default-500">{t('push_campaign.empty')}</p>
            <Button color="primary" onPress={createModal.onOpen}>
              {t('push_campaign.create_first')}
            </Button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-divider text-left text-default-400 text-xs uppercase tracking-wider">
                  <th className="px-4 py-3">{t('push_campaign.col_name')}</th>
                  <th className="px-4 py-3">{t('push_campaign.col_title')}</th>
                  <th className="px-4 py-3">{t('push_campaign.col_status')}</th>
                  <th className="px-4 py-3">{t('push_campaign.col_scheduled')}</th>
                  <th className="px-4 py-3">{t('push_campaign.col_radius')}</th>
                  <th className="px-4 py-3">{t('push_campaign.col_trust_tier')}</th>
                </tr>
              </thead>
              <tbody>
                {campaigns.map((campaign) => (
                  <tr key={campaign.id} className="border-b border-divider/50 hover:bg-default-50 transition-colors">
                    <td className="px-4 py-3 font-medium text-theme-primary">{campaign.name}</td>
                    <td className="px-4 py-3 text-default-600 max-w-[200px] truncate">{campaign.title}</td>
                    <td className="px-4 py-3">
                      <Chip
                        size="sm"
                        color={STATUS_COLOR[campaign.status] ?? 'default'}
                        variant="flat"
                      >
                        {t(`push_campaign.status_${campaign.status}`)}
                      </Chip>
                    </td>
                    <td className="px-4 py-3 text-default-500 whitespace-nowrap">
                      {campaign.schedule_at
                        ? new Date(campaign.schedule_at).toLocaleString()
                        : '—'}
                    </td>
                    <td className="px-4 py-3 text-default-500">
                      {campaign.audience_radius_km ? `${campaign.audience_radius_km} km` : '—'}
                    </td>
                    <td className="px-4 py-3 text-default-500 capitalize">
                      {t(`push_campaign.tier_${campaign.audience_min_trust_tier}`)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </GlassCard>

      {/* Create Push Campaign Modal */}
      <Modal isOpen={createModal.isOpen} onClose={handleModalClose} size="lg" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader>{t('push_campaign.create_campaign')}</ModalHeader>
          <ModalBody>
            <div className="space-y-4">
              <Input
                label={t('push_campaign.field_name')}
                placeholder={t('push_campaign.field_name_placeholder')}
                value={form.name}
                onValueChange={(v) => setForm((p) => ({ ...p, name: v }))}
                variant="bordered"
                isRequired
              />

              <Input
                label={t('push_campaign.field_title')}
                placeholder={t('push_campaign.field_title_placeholder')}
                value={form.title}
                onValueChange={(v) => setForm((p) => ({ ...p, title: v }))}
                variant="bordered"
                isRequired
                description={t('push_campaign.field_title_desc')}
              />

              <Textarea
                label={t('push_campaign.field_body')}
                placeholder={t('push_campaign.field_body_placeholder')}
                value={form.body}
                onValueChange={(v) => setForm((p) => ({ ...p, body: v }))}
                variant="bordered"
                isRequired
                minRows={3}
                description={t('push_campaign.field_body_desc')}
              />

              <Input
                label={t('push_campaign.field_schedule_at')}
                type="datetime-local"
                value={form.schedule_at}
                onValueChange={(v) => setForm((p) => ({ ...p, schedule_at: v }))}
                variant="bordered"
                description={t('push_campaign.field_schedule_at_desc')}
              />

              <Input
                label={t('push_campaign.field_radius')}
                placeholder="25"
                type="number"
                min="1"
                endContent={<span className="text-default-400 text-sm">km</span>}
                value={form.audience_radius_km}
                onValueChange={(v) => setForm((p) => ({ ...p, audience_radius_km: v }))}
                variant="bordered"
                description={t('push_campaign.field_radius_desc')}
              />

              <Select
                label={t('push_campaign.field_trust_tier')}
                selectedKeys={[form.audience_min_trust_tier]}
                onSelectionChange={(keys) => {
                  const v = Array.from(keys)[0] as TrustTier;
                  if (v) setForm((p) => ({ ...p, audience_min_trust_tier: v }));
                }}
                variant="bordered"
                description={t('push_campaign.field_trust_tier_desc')}
              >
                <SelectItem key="any">{t('push_campaign.tier_any')}</SelectItem>
                <SelectItem key="member">{t('push_campaign.tier_member')}</SelectItem>
                <SelectItem key="trusted">{t('push_campaign.tier_trusted')}</SelectItem>
                <SelectItem key="verified">{t('push_campaign.tier_verified')}</SelectItem>
              </Select>

              {/* Audience estimate */}
              <div className="rounded-lg border border-divider p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2 text-sm text-default-500">
                    <Users size={16} aria-hidden="true" />
                    <span>{t('push_campaign.estimate_label')}</span>
                  </div>
                  <Button
                    size="sm"
                    variant="flat"
                    onPress={handleEstimateAudience}
                    isLoading={isEstimating}
                  >
                    {t('push_campaign.estimate_button')}
                  </Button>
                </div>
                {audienceEstimate !== null && (
                  <p className="text-sm font-medium text-primary">
                    {t('push_campaign.estimate_result', { count: audienceEstimate })}
                  </p>
                )}
              </div>
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={handleModalClose} isDisabled={isCreating}>
              {t('cancel')}
            </Button>
            <Button color="primary" onPress={handleCreate} isLoading={isCreating}>
              {t('push_campaign.submit_create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default MyPushCampaignsPage;
