// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Campaigns Page (I7)
 *
 * Lists all ideation campaigns - grouped collections of related challenges.
 * Admin can create new campaigns.
 */

import { useState, useEffect, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Button,
  Spinner,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import {
  ArrowLeft,
  Layers,
  Plus,
  AlertTriangle,
  RefreshCw,
  Lightbulb,
  Calendar,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAssetUrl } from '@/lib/helpers';

/* ───────────────────────── Types ───────────────────────── */

interface Campaign {
  id: number;
  title: string;
  description: string | null;
  cover_image: string | null;
  challenges_count: number;
  status: string;
  created_at: string;
}

/* ───────────────────────── Main Component ───────────────────────── */

export function CampaignsPage() {
  const { t } = useTranslation('ideation');
  usePageTitle(t('campaigns.page_title'));
  const { user } = useAuth();
  const { tenantPath, hasFeature } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const { isOpen, onOpen, onClose } = useDisclosure();
  const [newCampaign, setNewCampaign] = useState({ name: '', description: '' });
  const [isCreating, setIsCreating] = useState(false);

  const isAdmin = user?.role && ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'].includes(user.role);

  const fetchCampaigns = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<Campaign[]>('/v2/ideation-campaigns');
      if (response.success && response.data) {
        setCampaigns(Array.isArray(response.data) ? response.data : []);
      }
    } catch (err) {
      logError('Failed to fetch campaigns', err);
      setError(t('challenges.load_error'));
    } finally {
      setIsLoading(false);
    }
  }, [t]);

  useEffect(() => {
    fetchCampaigns();
  }, [fetchCampaigns]);

  if (!hasFeature('ideation_challenges')) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] px-6 py-16 text-center">
        <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-100 to-yellow-100 dark:from-amber-900/30 dark:to-yellow-900/30 flex items-center justify-center mb-4">
          <Lightbulb className="w-8 h-8 text-amber-500" aria-hidden="true" />
        </div>
        <h2 className="text-xl font-semibold text-[var(--color-text)] mb-2">{t('campaigns.feature_not_available', 'Ideation Not Available')}</h2>
        <p className="text-[var(--color-text-muted)] max-w-sm">
          {t('campaigns.feature_not_available_desc', 'The ideation feature is not enabled for this community. Contact your timebank administrator to learn more.')}
        </p>
      </div>
    );
  }

  const handleCreate = async () => {
    if (!newCampaign.name.trim()) return;

    setIsCreating(true);
    try {
      const response = await api.post<Campaign>('/v2/ideation-campaigns', {
        title: newCampaign.name.trim(),
        description: newCampaign.description.trim() || null,
      });

      if (response.success && response.data) {
        toast.success(t('toast.campaign_created'));
        setNewCampaign({ name: '', description: '' });
        onClose();
        navigate(tenantPath(`/ideation/campaigns/${response.data.id}`));
      }
    } catch (err) {
      logError('Failed to create campaign', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setIsCreating(false);
    }
  };

  const formatDate = (dateStr: string) => {
    try {
      return new Date(dateStr).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
      });
    } catch {
      return dateStr;
    }
  };

  return (
    <div className="max-w-5xl mx-auto px-4 py-6">
      {/* Back link */}
      <Button
        variant="light"
        startContent={<ArrowLeft className="w-4 h-4" />}
        className="mb-4 -ml-2"
        onPress={() => navigate(tenantPath('/ideation'))}
      >
        {t('title')}
      </Button>

      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 className="text-2xl font-bold text-[var(--color-text)] flex items-center gap-3">
          <Layers className="w-7 h-7" />
          {t('campaigns.title')}
        </h1>
        {isAdmin && (
          <Button
            color="primary"
            startContent={<Plus className="w-4 h-4" />}
            onPress={onOpen}
          >
            {t('campaigns.create')}
          </Button>
        )}
      </div>

      {/* Loading */}
      {isLoading && (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      )}

      {/* Error */}
      {error && !isLoading && (
        <EmptyState
          icon={<AlertTriangle className="w-10 h-10 text-theme-subtle" />}
          title={t('challenges.load_error')}
          action={
            <Button
              color="primary"
              variant="flat"
              startContent={<RefreshCw className="w-4 h-4" />}
              onPress={fetchCampaigns}
            >
              {t('campaigns.retry', 'Retry')}
            </Button>
          }
        />
      )}

      {/* Empty */}
      {!isLoading && !error && campaigns.length === 0 && (
        <EmptyState
          icon={<Layers className="w-10 h-10 text-theme-subtle" />}
          title={t('campaigns.empty_title')}
          description={t('campaigns.empty_description')}
        />
      )}

      {/* Campaign List */}
      {!isLoading && !error && campaigns.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {campaigns.map((campaign) => (
            <Link
              key={campaign.id}
              to={tenantPath(`/ideation/campaigns/${campaign.id}`)}
              className="block"
            >
              <GlassCard className="h-full hover:shadow-lg transition-shadow cursor-pointer overflow-hidden">
                {campaign.cover_image && (
                  <div className="w-full h-32 overflow-hidden">
                    <img
                      src={resolveAssetUrl(campaign.cover_image)}
                      alt={campaign.title}
                      className="w-full h-full object-cover"
                      loading="lazy"
                    />
                  </div>
                )}
                <div className="p-5">
                  <h3 className="text-lg font-semibold text-[var(--color-text)] mb-2">
                    {campaign.title}
                  </h3>
                  {campaign.description && (
                    <p className="text-sm text-[var(--color-text-secondary)] mb-3 line-clamp-2">
                      {campaign.description}
                    </p>
                  )}
                  <div className="flex items-center gap-3 text-xs text-[var(--color-text-tertiary)]">
                    <span className="flex items-center gap-1">
                      <Lightbulb className="w-3.5 h-3.5" />
                      {t('campaigns.challenges_count', { count: campaign.challenges_count })}
                    </span>
                    <span className="flex items-center gap-1">
                      <Calendar className="w-3.5 h-3.5" />
                      {formatDate(campaign.created_at)}
                    </span>
                  </div>
                </div>
              </GlassCard>
            </Link>
          ))}
        </div>
      )}

      {/* Create Campaign Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg">
        <ModalContent>
          <ModalHeader>{t('campaigns.create')}</ModalHeader>
          <ModalBody>
            <Input
              label={t('form.title_label')}
              placeholder={t('form.title_placeholder')}
              value={newCampaign.name}
              onValueChange={(val) => setNewCampaign(prev => ({ ...prev, name: val }))}
              variant="bordered"
              isRequired
            />
            <Textarea
              label={t('form.description_label')}
              placeholder={t('form.description_placeholder')}
              value={newCampaign.description}
              onValueChange={(val) => setNewCampaign(prev => ({ ...prev, description: val }))}
              variant="bordered"
              minRows={3}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose}>
              {t('form.cancel')}
            </Button>
            <Button
              color="primary"
              isLoading={isCreating}
              isDisabled={!newCampaign.name.trim()}
              onPress={handleCreate}
            >
              {isCreating ? t('form.creating') : t('campaigns.create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default CampaignsPage;
