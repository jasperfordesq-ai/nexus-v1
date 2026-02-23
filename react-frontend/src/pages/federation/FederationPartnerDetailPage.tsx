// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Partner Detail Page
 *
 * Shows a partner community's profile from the federation network.
 * Route: /federation/partners/:id
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Avatar,
  Chip,
  Spinner,
} from '@heroui/react';
import {
  Globe,
  MapPin,
  Users,
  ArrowLeft,
  AlertTriangle,
  RefreshCw,
  Shield,
  MessageSquare,
  ArrowRightLeft,
  ListTodo,
  Calendar,
  UserCheck,
  Handshake,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { useAuth, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { FederationPartner } from '@/types/api';

// ─────────────────────────────────────────────────────────────────────────────
// Federation Level Metadata
// ─────────────────────────────────────────────────────────────────────────────

interface FederationLevelMeta {
  label: string;
  className: string;
}

const FEDERATION_LEVELS: Record<number, FederationLevelMeta> = {
  1: {
    label: 'Discovery',
    className: 'bg-blue-500/20 text-blue-600 dark:text-blue-400',
  },
  2: {
    label: 'Social',
    className: 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400',
  },
  3: {
    label: 'Economic',
    className: 'bg-purple-500/20 text-purple-600 dark:text-purple-400',
  },
  4: {
    label: 'Integrated',
    className: 'bg-amber-500/20 text-amber-600 dark:text-amber-400',
  },
};

/** Map permission keys to display labels and icons */
const PERMISSION_META: Record<string, { label: string; icon: typeof Globe }> = {
  profiles: { label: 'View Profiles', icon: UserCheck },
  messaging: { label: 'Cross-Community Messaging', icon: MessageSquare },
  transactions: { label: 'Time Credit Transfers', icon: ArrowRightLeft },
  listings: { label: 'Browse Listings', icon: ListTodo },
  events: { label: 'Shared Events', icon: Calendar },
};

// ─────────────────────────────────────────────────────────────────────────────
// Page Component
// ─────────────────────────────────────────────────────────────────────────────

export function FederationPartnerDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();

  const [partner, setPartner] = useState<FederationPartner | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  usePageTitle(partner?.name ?? 'Partner Community');

  const loadPartner = useCallback(async () => {
    if (!id) return;
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<FederationPartner[]>('/v2/federation/partners');
      if (response.success && response.data) {
        const found = response.data.find((p) => String(p.id) === id);
        if (found) {
          setPartner(found);
        } else {
          setError('Partner community not found or not accessible.');
        }
      } else {
        setError('Failed to load partner communities.');
      }
    } catch (err) {
      logError('Failed to load federation partner', err);
      setError('Failed to load partner community. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadPartner();
  }, [loadPartner]);

  // Loading
  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" label="Loading partner community..." />
      </div>
    );
  }

  // Error / Not found
  if (error || !partner) {
    return (
      <div className="space-y-6">
        <Breadcrumbs
          items={[
            { label: 'Federation', href: tenantPath('/federation') },
            { label: 'Partners', href: tenantPath('/federation/partners') },
            { label: 'Not Found' },
          ]}
        />
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">
            Partner Not Found
          </h2>
          <p className="text-theme-muted mb-4">
            {error || 'This partner community could not be found or is not accessible from your community.'}
          </p>
          <div className="flex gap-3 justify-center">
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              onPress={() => navigate(tenantPath('/federation/partners'))}
            >
              Back to Partners
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={loadPartner}
            >
              Try Again
            </Button>
          </div>
        </GlassCard>
      </div>
    );
  }

  const levelMeta = FEDERATION_LEVELS[partner.federation_level];

  return (
    <div className="space-y-6">
      {/* Breadcrumbs */}
      <Breadcrumbs
        items={[
          { label: 'Federation', href: tenantPath('/federation') },
          { label: 'Partners', href: tenantPath('/federation/partners') },
          { label: partner.name },
        ]}
      />

      {/* Profile Header */}
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }}>
        <GlassCard className="p-6 md:p-8">
          <div className="flex flex-col sm:flex-row items-start gap-6">
            {/* Avatar */}
            <div className="relative flex-shrink-0">
              <Avatar
                name={partner.name[0]}
                src={partner.logo || undefined}
                className="w-24 h-24 ring-4 ring-indigo-500/20 bg-gradient-to-br from-indigo-500 to-purple-600 text-white text-3xl"
              />
              <div
                className="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-indigo-500 flex items-center justify-center ring-2 ring-white dark:ring-gray-900"
                title="Federation Partner"
              >
                <Handshake className="w-4 h-4 text-white" aria-hidden="true" />
              </div>
            </div>

            {/* Info */}
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-bold text-theme-primary">
                {partner.name}
              </h1>

              {partner.tagline && (
                <p className="text-theme-muted mt-1">{partner.tagline}</p>
              )}

              {/* Federation Level */}
              <Chip
                size="sm"
                variant="flat"
                className={`mt-3 ${levelMeta?.className || 'bg-theme-hover text-theme-muted'}`}
              >
                Level {partner.federation_level} &mdash;{' '}
                {partner.federation_level_name || levelMeta?.label || 'Unknown'}
              </Chip>

              {/* Meta row */}
              <div className="flex flex-wrap items-center gap-4 mt-3 text-sm text-theme-muted">
                {partner.location && (
                  <span className="flex items-center gap-1.5">
                    <MapPin className="w-4 h-4" aria-hidden="true" />
                    {partner.location}
                    {partner.country && `, ${partner.country}`}
                  </span>
                )}
                <span className="flex items-center gap-1.5">
                  <Users className="w-4 h-4" aria-hidden="true" />
                  {partner.member_count.toLocaleString()} members
                </span>
                {partner.partnership_since && (
                  <span className="flex items-center gap-1.5">
                    <Shield className="w-4 h-4" aria-hidden="true" />
                    Partner since{' '}
                    {new Date(partner.partnership_since).toLocaleDateString('en-US', {
                      month: 'long',
                      year: 'numeric',
                    })}
                  </span>
                )}
              </div>

              {/* Actions */}
              <div className="flex flex-wrap gap-3 mt-4">
                {isAuthenticated && (
                  <>
                    <Link to={tenantPath(`/federation/members?partner_id=${partner.id}`)}>
                      <Button
                        className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                        startContent={<Users className="w-4 h-4" aria-hidden="true" />}
                      >
                        Browse Members
                      </Button>
                    </Link>
                    <Link to={tenantPath(`/federation/listings?partner_id=${partner.id}`)}>
                      <Button
                        variant="flat"
                        className="bg-theme-elevated text-theme-primary"
                        startContent={<ListTodo className="w-4 h-4" aria-hidden="true" />}
                      >
                        Browse Listings
                      </Button>
                    </Link>
                  </>
                )}
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
                  onPress={() => navigate(tenantPath('/federation/partners'))}
                >
                  Back to Partners
                </Button>
              </div>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Available Features */}
      {partner.permissions && partner.permissions.length > 0 && (
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }}>
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-theme-primary mb-3 flex items-center gap-2">
              <Globe className="w-5 h-5 text-indigo-500" aria-hidden="true" />
              Available Features
            </h2>
            <div className="flex flex-wrap gap-2">
              {partner.permissions.map((perm) => {
                const meta = PERMISSION_META[perm];
                const Icon = meta?.icon || Globe;
                return (
                  <Chip
                    key={perm}
                    variant="flat"
                    className="bg-theme-hover text-theme-muted"
                    startContent={<Icon className="w-3.5 h-3.5" aria-hidden="true" />}
                  >
                    {meta?.label || perm}
                  </Chip>
                );
              })}
            </div>
          </GlassCard>
        </motion.div>
      )}
    </div>
  );
}

export default FederationPartnerDetailPage;
