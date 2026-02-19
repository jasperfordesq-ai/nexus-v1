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
  usePageTitle('Request Exchange');
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
        const estimatedHours = listingResponse.data.hours_estimate || listingResponse.data.estimated_hours;
        if (estimatedHours) {
          setProposedHours(estimatedHours.toString());
        }
      } else {
        setError('Listing not found');
      }

      if (configResponse.success && configResponse.data) {
        setConfig(configResponse.data);
        if (!configResponse.data.exchange_workflow_enabled) {
          setError('Exchange workflow is not enabled');
        }
      }
    } catch (err) {
      setError('Failed to load listing');
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
      toast.error('Please enter valid hours');
      return;
    }

    if (hours > MAX_EXCHANGE_HOURS) {
      toast.error(`Maximum ${MAX_EXCHANGE_HOURS} hours per exchange`);
      return;
    }

    try {
      setIsSubmitting(true);
      const response = await api.post<{ id: number }>('/v2/exchanges', {
        listing_id: listing.id,
        proposed_hours: hours,
        message: message.trim() || undefined,
      });

      if (response.success && response.data) {
        toast.success('Exchange request sent!');
        navigate(tenantPath(`/exchanges/${response.data.id}`));
      }
    } catch (err) {
      toast.error('Failed to create exchange request');
      logError('Failed to create exchange request', err);
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading) {
    return <LoadingScreen message="Loading..." />;
  }

  if (error || !listing || !config?.exchange_workflow_enabled) {
    return (
      <EmptyState
        icon={<ArrowRightLeft className="w-12 h-12" />}
        title={error || 'Cannot Request Exchange'}
        description="This listing is not available for exchange requests."
        action={
          <Link to={tenantPath("/listings")}>
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
              Browse Listings
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
        title="This is Your Listing"
        description="You cannot request an exchange for your own listing."
        action={
          <Link to={tenantPath(`/listings/${listing.id}`)}>
            <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
              View Listing
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
        { label: 'Listings', href: tenantPath('/listings') },
        { label: listing?.title || 'Listing', href: id ? tenantPath(`/listings/${id}`) : tenantPath('/listings') },
        { label: 'Request Exchange' },
      ]} />

      {/* Header */}
      <div className="text-center">
        <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500/20 mb-4">
          <ArrowRightLeft className="w-8 h-8 text-emerald-400" aria-hidden="true" />
        </div>
        <h1 className="text-2xl sm:text-3xl font-bold text-theme-primary">
          Request Exchange
        </h1>
        <p className="text-theme-muted mt-2">
          Send a request to exchange time credits for this service
        </p>
      </div>

      {/* Listing Summary */}
      <GlassCard className="p-6">
        <div className="flex items-start gap-4">
          <Avatar
            src={resolveAvatarUrl(listing.user?.avatar)}
            name={listing.user?.name || 'Unknown'}
            size="lg"
          />
          <div className="flex-1 min-w-0">
            <h2 className="text-lg font-semibold text-theme-primary">
              {listing.title}
            </h2>
            <div className="flex flex-wrap items-center gap-4 mt-2 text-sm text-theme-muted">
              <span className="flex items-center gap-1">
                <User className="w-4 h-4" aria-hidden="true" />
                {listing.user?.name || 'Unknown'}
              </span>
              {listing.category_name && (
                <span className="flex items-center gap-1">
                  <Tag className="w-4 h-4" aria-hidden="true" />
                  {listing.category_name}
                </span>
              )}
              <span className="flex items-center gap-1">
                <Clock className="w-4 h-4" aria-hidden="true" />
                ~{listing.hours_estimate || listing.estimated_hours || '?'} hours
              </span>
            </div>
            <span className={`
              inline-block mt-2 text-xs px-2 py-1 rounded-full
              ${listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}
            `}>
              {listing.type === 'offer' ? 'Service Offer' : 'Service Request'}
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
              label="Proposed Hours"
              placeholder="How many hours do you need?"
              value={proposedHours}
              onChange={(e) => setProposedHours(e.target.value)}
              min="0.5"
              max={MAX_EXCHANGE_HOURS}
              step="0.5"
              isRequired
              endContent={<span className="text-theme-muted">hours</span>}
              description={`The estimated time for this service (max: ${MAX_EXCHANGE_HOURS})`}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </div>

          <div>
            <Textarea
              label="Message (optional)"
              placeholder="Add a message to the service provider..."
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              minRows={3}
              maxRows={6}
              description="Explain your needs or ask any questions"
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </div>

          {config.require_broker_approval && (
            <div className="bg-amber-500/10 rounded-lg p-4 text-sm">
              <p className="text-amber-400 font-medium">Broker Approval Required</p>
              <p className="text-theme-muted mt-1">
                This exchange will need to be approved by a community coordinator before it can proceed.
              </p>
            </div>
          )}

          <div className="flex gap-3 pt-4">
            <Button
              type="button"
              variant="flat"
              className="flex-1 bg-theme-elevated text-theme-primary"
              onClick={() => navigate(tenantPath(id ? `/listings/${id}` : '/listings'))}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              className="flex-1 bg-gradient-to-r from-emerald-500 to-teal-600 text-white"
              startContent={<Send className="w-4 h-4" aria-hidden="true" />}
              isLoading={isSubmitting}
            >
              Send Request
            </Button>
          </div>
        </form>
      </GlassCard>
    </motion.div>
  );
}

export default RequestExchangePage;
