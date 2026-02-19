// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Partners Page - Browse partner communities in the federation network
 *
 * Features:
 * - Partner cards grid (2-col desktop, 1-col mobile)
 * - Federation level badges (Discovery, Social, Economic, Integrated)
 * - Partner detail modal with available features and navigation links
 * - Loading skeletons and error states
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Avatar,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import {
  Globe,
  MapPin,
  Users,
  Eye,
  RefreshCw,
  AlertTriangle,
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
import { EmptyState } from '@/components/feedback';
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
  color: 'primary' | 'success' | 'secondary' | 'warning';
  className: string;
}

const FEDERATION_LEVELS: Record<number, FederationLevelMeta> = {
  1: {
    label: 'Discovery',
    color: 'primary',
    className: 'bg-blue-500/20 text-blue-600 dark:text-blue-400',
  },
  2: {
    label: 'Social',
    color: 'success',
    className: 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400',
  },
  3: {
    label: 'Economic',
    color: 'secondary',
    className: 'bg-purple-500/20 text-purple-600 dark:text-purple-400',
  },
  4: {
    label: 'Integrated',
    color: 'warning',
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

export function FederationPartnersPage() {
  usePageTitle('Partner Communities');
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();

  const [partners, setPartners] = useState<FederationPartner[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [selectedPartner, setSelectedPartner] = useState<FederationPartner | null>(null);
  const [isDetailOpen, setIsDetailOpen] = useState(false);

  const loadPartners = useCallback(async () => {
    try {
      setIsLoading(true);
      setLoadError(null);

      const response = await api.get<FederationPartner[]>('/v2/federation/partners');

      if (response.success && response.data) {
        setPartners(response.data);
      } else {
        setPartners([]);
      }
    } catch (error) {
      logError('Failed to load federation partners', error);
      setLoadError('Failed to load partner communities. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadPartners();
  }, [loadPartners]);

  function openDetail(partner: FederationPartner) {
    setSelectedPartner(partner);
    setIsDetailOpen(true);
  }

  function closeDetail() {
    setIsDetailOpen(false);
    setSelectedPartner(null);
  }

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.08 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-6">
      {/* Breadcrumbs */}
      <Breadcrumbs
        items={[
          { label: 'Federation', href: '/federation' },
          { label: 'Partner Communities' },
        ]}
      />

      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <Handshake className="w-7 h-7 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
          Partner Communities
        </h1>
        <p className="text-theme-muted mt-1">
          Explore the timebanks in your federation network
        </p>
      </div>

      {/* Loading State */}
      {isLoading && (
        <div className="grid md:grid-cols-2 gap-4">
          {[1, 2, 3, 4].map((i) => (
            <GlassCard key={i} className="p-6 animate-pulse">
              <div className="flex items-start gap-4">
                <div className="w-14 h-14 rounded-full bg-theme-hover" />
                <div className="flex-1">
                  <div className="h-5 bg-theme-hover rounded w-1/2 mb-2" />
                  <div className="h-4 bg-theme-hover rounded w-3/4 mb-3" />
                  <div className="h-3 bg-theme-hover rounded w-1/3" />
                </div>
              </div>
            </GlassCard>
          ))}
        </div>
      )}

      {/* Error State */}
      {!isLoading && loadError && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">
            Unable to Load Partners
          </h2>
          <p className="text-theme-muted mb-4">{loadError}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={loadPartners}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Empty State */}
      {!isLoading && !loadError && partners.length === 0 && (
        <EmptyState
          icon={<Globe className="w-12 h-12" />}
          title="No partner communities yet"
          description="Your community has not established any federation partnerships yet."
        />
      )}

      {/* Partners Grid */}
      {!isLoading && !loadError && partners.length > 0 && (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="grid md:grid-cols-2 gap-4"
        >
          {partners.map((partner) => (
            <motion.div key={partner.id} variants={itemVariants}>
              <PartnerCard partner={partner} onViewDetails={() => openDetail(partner)} />
            </motion.div>
          ))}
        </motion.div>
      )}

      {/* Partner Detail Modal */}
      <Modal
        isOpen={isDetailOpen}
        onOpenChange={(open) => { if (!open) closeDetail(); }}
        size="2xl"
        backdrop="blur"
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-4',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {selectedPartner && (
            <>
              <ModalHeader className="flex items-center gap-3">
                <Avatar
                  name={selectedPartner.name[0]}
                  src={selectedPartner.logo || undefined}
                  size="md"
                  className="bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex-shrink-0"
                />
                <div>
                  <h2 className="text-lg font-bold text-theme-primary">
                    {selectedPartner.name}
                  </h2>
                  {selectedPartner.tagline && (
                    <p className="text-sm text-theme-muted font-normal">
                      {selectedPartner.tagline}
                    </p>
                  )}
                </div>
              </ModalHeader>
              <ModalBody>
                <div className="space-y-5">
                  {/* Stats Row */}
                  <div className="flex flex-wrap gap-4 text-sm">
                    {selectedPartner.location && (
                      <span className="flex items-center gap-1.5 text-theme-muted">
                        <MapPin className="w-4 h-4" aria-hidden="true" />
                        {selectedPartner.location}
                        {selectedPartner.country && `, ${selectedPartner.country}`}
                      </span>
                    )}
                    <span className="flex items-center gap-1.5 text-theme-muted">
                      <Users className="w-4 h-4" aria-hidden="true" />
                      {selectedPartner.member_count.toLocaleString()} members
                    </span>
                    {selectedPartner.partnership_since && (
                      <span className="flex items-center gap-1.5 text-theme-muted">
                        <Shield className="w-4 h-4" aria-hidden="true" />
                        Partner since{' '}
                        {new Date(selectedPartner.partnership_since).toLocaleDateString('en-US', {
                          month: 'long',
                          year: 'numeric',
                        })}
                      </span>
                    )}
                  </div>

                  {/* Federation Level */}
                  <div>
                    <h3 className="text-sm font-semibold text-theme-primary mb-2">
                      Federation Level
                    </h3>
                    <Chip
                      variant="flat"
                      className={
                        FEDERATION_LEVELS[selectedPartner.federation_level]?.className ||
                        'bg-theme-hover text-theme-muted'
                      }
                    >
                      Level {selectedPartner.federation_level} &mdash;{' '}
                      {selectedPartner.federation_level_name ||
                        FEDERATION_LEVELS[selectedPartner.federation_level]?.label ||
                        'Unknown'}
                    </Chip>
                  </div>

                  {/* Available Features */}
                  {selectedPartner.permissions && selectedPartner.permissions.length > 0 && (
                    <div>
                      <h3 className="text-sm font-semibold text-theme-primary mb-2">
                        Available Features
                      </h3>
                      <div className="flex flex-wrap gap-2">
                        {selectedPartner.permissions.map((perm) => {
                          const meta = PERMISSION_META[perm];
                          const Icon = meta?.icon || Globe;
                          return (
                            <Chip
                              key={perm}
                              variant="flat"
                              size="sm"
                              className="bg-theme-hover text-theme-muted"
                              startContent={
                                <Icon className="w-3.5 h-3.5" aria-hidden="true" />
                              }
                            >
                              {meta?.label || perm}
                            </Chip>
                          );
                        })}
                      </div>
                    </div>
                  )}
                </div>
              </ModalBody>
              <ModalFooter className="flex gap-2">
                {isAuthenticated && (
                  <>
                    <Link to={tenantPath(`/federation/members?partner_id=${selectedPartner.id}`)}>
                      <Button
                        variant="flat"
                        className="bg-theme-elevated text-theme-primary"
                        startContent={<Users className="w-4 h-4" aria-hidden="true" />}
                        onPress={closeDetail}
                      >
                        Browse Members
                      </Button>
                    </Link>
                    <Link to={tenantPath(`/federation/listings?partner_id=${selectedPartner.id}`)}>
                      <Button
                        className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                        startContent={<ListTodo className="w-4 h-4" aria-hidden="true" />}
                        onPress={closeDetail}
                      >
                        Browse Listings
                      </Button>
                    </Link>
                  </>
                )}
                {!isAuthenticated && (
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={closeDetail}
                  >
                    Close
                  </Button>
                )}
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Partner Card Component
// ─────────────────────────────────────────────────────────────────────────────

interface PartnerCardProps {
  partner: FederationPartner;
  onViewDetails: () => void;
}

function PartnerCard({ partner, onViewDetails }: PartnerCardProps) {
  const levelMeta = FEDERATION_LEVELS[partner.federation_level];

  return (
    <GlassCard className="p-5 hover:scale-[1.01] transition-transform h-full flex flex-col">
      <div className="flex items-start gap-4 mb-4">
        {/* Logo / Avatar */}
        <Avatar
          name={partner.name[0]}
          src={partner.logo || undefined}
          size="lg"
          className="bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex-shrink-0"
        />
        <div className="flex-1 min-w-0">
          <h3 className="font-semibold text-theme-primary text-lg truncate">
            {partner.name}
          </h3>
          {partner.tagline && (
            <p className="text-theme-muted text-sm line-clamp-2 mt-0.5">
              {partner.tagline}
            </p>
          )}
        </div>
      </div>

      {/* Meta Info */}
      <div className="flex flex-wrap items-center gap-3 text-sm text-theme-subtle mb-4 flex-1">
        {partner.location && (
          <span className="flex items-center gap-1">
            <MapPin className="w-3.5 h-3.5" aria-hidden="true" />
            {partner.location}
          </span>
        )}
        <span className="flex items-center gap-1">
          <Users className="w-3.5 h-3.5" aria-hidden="true" />
          {partner.member_count.toLocaleString()} members
        </span>
      </div>

      {/* Footer: Level + Action */}
      <div className="flex items-center justify-between pt-3 border-t border-theme-default">
        <div className="flex items-center gap-2">
          <Chip
            size="sm"
            variant="flat"
            className={levelMeta?.className || 'bg-theme-hover text-theme-muted'}
          >
            {levelMeta?.label || partner.federation_level_name || `Level ${partner.federation_level}`}
          </Chip>
          {partner.partnership_since && (
            <span className="text-xs text-theme-subtle">
              Since{' '}
              {new Date(partner.partnership_since).toLocaleDateString('en-US', {
                month: 'short',
                year: 'numeric',
              })}
            </span>
          )}
        </div>
        <Button
          size="sm"
          variant="flat"
          className="bg-theme-elevated text-theme-primary"
          startContent={<Eye className="w-3.5 h-3.5" aria-hidden="true" />}
          onPress={onViewDetails}
        >
          View Details
        </Button>
      </div>
    </GlassCard>
  );
}

export default FederationPartnersPage;
