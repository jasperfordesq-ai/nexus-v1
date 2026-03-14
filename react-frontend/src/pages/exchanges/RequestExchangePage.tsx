// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Request Exchange Page - Create a new exchange request for a listing
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar, Input, Textarea } from '@heroui/react';
import {
  ArrowRightLeft,
  Clock,
  User,
  Tag,
  Send,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { MAX_EXCHANGE_HOURS } from '@/lib/exchange-status';
import type { Listing, ExchangeConfig } from '@/types/api';

export function RequestExchangePage() {
  const { t } = useTranslation('exchanges');
  usePageTitle(t('request.page_title'));
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [listing, setListing] = useState<Listing | null>(null);
  const [config, setConfig] = useState<ExchangeConfig | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [proposedHours, setProposedHours] = useState('');
  const [prepTime, setPrepTime] = useState('');
  const [message, setMessage] = useState('');

  const loadData = useCallback(async () => {
    if (!id) return;

    try {
      setIsLoading(true);
      setError(null);

      // Load listing and config in parallel
      const [listingResponse, configResponse] = await Promise.all([
        api.get<Listing>(`/v2/listings/${id}`),
        api.get<ExchangeConfig>('/v2/exchanges/config'),
      ]);

      if (listingResponse.success && listingResponse.data) {
        setListing(listingResponse.data);
        // Pre-fill hours with listing estimate
        const estimatedHours = listingResponse.data?.hours_estimate || listingResponse.data?.estimated_hours;
        if (estimatedHours) {
          setProposedHours(estimatedHours.toString());
        }
      } else {
        setError(t('request.listing_not_found'));
      }

      if (configResponse.success && configResponse.data) {
        setConfig(configResponse.data);
        if (!configResponse.data?.exchange_workflow_enabled) {
          setError(t('request.workflow_not_enabled'));
        }
      }
    } catch (err) {
      setError(t('request.load_failed'));
      logError('Failed to load listing', err);
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();

    if (!listing) return;

    const hours = parseFloat(proposedHours);
    if (isNaN(hours) || hours <= 0) {
      toast.error(t('toast.invalid_hours'));
      return;
    }

    if (hours > MAX_EXCHANGE_HOURS) {
      toast.error(t('toast.max_hours', { max: MAX_EXCHANGE_HOURS }));
      return;
    }

    try {
      setIsSubmitting(true);
      const prepTimeVal = parseFloat(prepTime);
      const response = await api.post<{ id: number }>('/v2/exchanges', {
        listing_id: listing.id,
        proposed_hours: hours,
        prep_time: !isNaN(prepTimeVal) && prepTimeVal > 0 ? prepTimeVal : undefined,
        message: message.trim() || undefined,
      });

      if (response.success && response.data) {
        toast.success(t('toast.request_sent'));
        navigate(tenantPath(`/exchanges/${response.data.id}`));
      }
    } catch (err) {
      toast.error(t('toast.request_failed'));
      logError('Failed to create exchange request', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading) {
    return <LoadingScreen message={t('request.loading')} />;
  }

  if (error || !listing || !config?.exchange_workflow_enabled) {
    return (
      <EmptyState
        icon={<ArrowRightLeft className="w-12 h-12" />}
        title={error || t('request.cannot_request')}
        description={t('request.not_available')}
        action={
          <Link to={tenantPath("/listings")}>
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
              {t('browse_listings')}
            </Button>
          </Link>
        }
      />
    );
  }

  // Can't request your own listing
  if (listing.user_id === user?.id) {
    return (
      <EmptyState
        icon={<ArrowRightLeft className="w-12 h-12" />}
        title={t('request.own_listing_title')}
        description={t('request.own_listing_description')}
        action={
          <Link to={tenantPath(`/listings/${listing.id}`)}>
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
              {t('request.view_listing')}
            </Button>
          </Link>
        }
      />
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-2xl mx-auto space-y-6"
    >
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: t('request.breadcrumb_listings'), href: tenantPath('/listings') },
        { label: listing?.title || t('request.breadcrumb_listing'), href: id ? tenantPath(`/listings/${id}`) : tenantPath('/listings') },
        { label: t('request.breadcrumb_request') },
      ]} />

      {/* Header */}
      <div className="text-center">
        <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500/20 mb-4">
          <ArrowRightLeft className="w-8 h-8 text-emerald-400" aria-hidden="true" />
        </div>
        <h1 className="text-2xl sm:text-3xl font-bold text-theme-primary">
          {t('request.title')}
        </h1>
        <p className="text-theme-muted mt-2">
          {t('request.subtitle')}
        </p>
      </div>

      {/* Listing Summary */}
      <GlassCard className="p-6">
        <div className="flex items-start gap-4">
          <Avatar
            src={resolveAvatarUrl(listing.user?.avatar)}
            name={listing.user?.name || t('unknown')}
            size="lg"
          />
          <div className="flex-1 min-w-0">
            <h2 className="text-lg font-semibold text-theme-primary">
              {listing.title}
            </h2>
            <div className="flex flex-wrap items-center gap-4 mt-2 text-sm text-theme-muted">
              <span className="flex items-center gap-1">
                <User className="w-4 h-4" aria-hidden="true" />
                {listing.user?.name || t('unknown')}
              </span>
              {listing.category_name && (
                <span className="flex items-center gap-1">
                  <Tag className="w-4 h-4" aria-hidden="true" />
                  {listing.category_name}
                </span>
              )}
              <span className="flex items-center gap-1">
                <Clock className="w-4 h-4" aria-hidden="true" />
                {t('request.estimated_hours', { hours: listing.hours_estimate || listing.estimated_hours || '?' })}
              </span>
            </div>
            <span className={`
              inline-block mt-2 text-xs px-2 py-1 rounded-full
              ${listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}
            `}>
              {listing.type === 'offer' ? t('request.service_offer') : t('request.service_request')}
            </span>
          </div>
        </div>
      </GlassCard>

      {/* Request Form */}
      <GlassCard className="p-6">
        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <Input
              type="number"
              label={t('request.proposed_hours_label')}
              placeholder={t('request.proposed_hours_placeholder')}
              value={proposedHours}
              onChange={(e) => setProposedHours(e.target.value)}
              min="0.5"
              max={MAX_EXCHANGE_HOURS}
              step="0.5"
              isRequired
              endContent={<span className="text-theme-muted">{t('request.hours_unit')}</span>}
              description={t('request.proposed_hours_description', { max: MAX_EXCHANGE_HOURS })}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </div>

          <div>
            <Input
              type="number"
              label={t('request.prep_time_label', 'Preparation Time')}
              placeholder={t('request.prep_time_placeholder', '0')}
              value={prepTime}
              onChange={(e) => setPrepTime(e.target.value)}
              min="0"
              max="10"
              step="0.25"
              endContent={<span className="text-theme-muted">{t('request.hours_unit')}</span>}
              description={t('request.prep_time_description', 'Additional time needed for preparation (optional)')}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </div>

          <div>
            <Textarea
              label={t('request.message_label')}
              placeholder={t('request.message_placeholder')}
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              minRows={3}
              maxRows={6}
              description={t('request.message_description')}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </div>

          {config.require_broker_approval && (
            <div className="bg-amber-500/10 rounded-lg p-4 text-sm">
              <p className="text-amber-400 font-medium">{t('request.broker_approval_title')}</p>
              <p className="text-theme-muted mt-1">
                {t('request.broker_approval_description')}
              </p>
            </div>
          )}

          <div className="flex gap-3 pt-4">
            <Button
              type="button"
              variant="flat"
              className="flex-1 bg-theme-elevated text-theme-primary"
              onPress={() => navigate(tenantPath(id ? `/listings/${id}` : '/listings'))}
            >
              {t('request.cancel')}
            </Button>
            <Button
              type="submit"
              className="flex-1 bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
              startContent={<Send className="w-4 h-4" aria-hidden="true" />}
              isLoading={isSubmitting}
            >
              {t('request.send_request')}
            </Button>
          </div>
        </form>
      </GlassCard>
    </motion.div>
  );
}

export default RequestExchangePage;
