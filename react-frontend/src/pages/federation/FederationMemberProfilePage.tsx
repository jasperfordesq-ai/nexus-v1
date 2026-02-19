// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Member Profile Page
 *
 * Shows a federated member's profile from a partner community.
 * Route: /federation/members/:id
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
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
  MessageSquare,
  ArrowLeft,
  AlertTriangle,
  RefreshCw,
  Home,
  Compass,
  Car,
  User,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { useAuth, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { resolveAvatarUrl } from '@/lib/helpers';
import { logError } from '@/lib/logger';
import type { FederatedMember } from '@/types/api';

const SERVICE_REACH_META: Record<string, { label: string; icon: typeof Home }> = {
  local_only: { label: 'Local Only', icon: Home },
  remote_ok: { label: 'Remote OK', icon: Compass },
  travel_ok: { label: 'Will Travel', icon: Car },
};

export function FederationMemberProfilePage() {
  usePageTitle('Member Profile');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();

  const [member, setMember] = useState<FederatedMember | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadMember = useCallback(async () => {
    if (!id) return;
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<FederatedMember>(`/v2/federation/members/${id}`);
      if (response.success && response.data) {
        setMember(response.data);
      } else {
        setError('Member not found or not accessible.');
      }
    } catch (err) {
      logError('Failed to load federated member profile', err);
      setError('Failed to load member profile. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadMember();
  }, [loadMember]);

  const displayName = member
    ? (member.name?.trim() || `${member.first_name || ''} ${member.last_name || ''}`.trim() || 'Member')
    : 'Member';

  const reachKey = member?.service_reach ?? 'local_only';
  const reachMeta = SERVICE_REACH_META[reachKey] ?? SERVICE_REACH_META.local_only;
  const ReachIcon = reachMeta.icon;

  // Loading
  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" label="Loading member profile..." />
      </div>
    );
  }

  // Error
  if (error || !member) {
    return (
      <div className="space-y-6">
        <Breadcrumbs
          items={[
            { label: 'Federation', href: tenantPath('/federation') },
            { label: 'Members', href: tenantPath('/federation/members') },
            { label: 'Profile' },
          ]}
        />
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">
            Member Not Found
          </h2>
          <p className="text-theme-muted mb-4">
            {error || 'This federated member could not be found or is not accessible from your community.'}
          </p>
          <div className="flex gap-3 justify-center">
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              onPress={() => navigate(tenantPath('/federation/members'))}
            >
              Back to Members
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={loadMember}
            >
              Try Again
            </Button>
          </div>
        </GlassCard>
      </div>
    );
  }

  const skills = member.skills ?? [];

  return (
    <div className="space-y-6">
      {/* Breadcrumbs */}
      <Breadcrumbs
        items={[
          { label: 'Federation', href: tenantPath('/federation') },
          { label: 'Members', href: tenantPath('/federation/members') },
          { label: displayName },
        ]}
      />

      {/* Profile Header */}
      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }}>
        <GlassCard className="p-6 md:p-8">
          <div className="flex flex-col sm:flex-row items-start gap-6">
            {/* Avatar */}
            <div className="relative flex-shrink-0">
              <Avatar
                src={resolveAvatarUrl(member.avatar)}
                name={displayName}
                className="w-24 h-24 ring-4 ring-indigo-500/20"
              />
              <div
                className="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-indigo-500 flex items-center justify-center ring-2 ring-white dark:ring-gray-900"
                title={member.timebank.name}
              >
                <Globe className="w-4 h-4 text-white" aria-hidden="true" />
              </div>
            </div>

            {/* Info */}
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-bold text-theme-primary">
                {displayName}
              </h1>

              {/* Community badge */}
              <Chip
                size="sm"
                variant="flat"
                className="mt-2 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                startContent={<Globe className="w-3.5 h-3.5" aria-hidden="true" />}
              >
                {member.timebank.name}
              </Chip>

              {/* Meta row */}
              <div className="flex flex-wrap items-center gap-4 mt-3 text-sm text-theme-muted">
                {member.location && (
                  <span className="flex items-center gap-1.5">
                    <MapPin className="w-4 h-4" aria-hidden="true" />
                    {member.location}
                  </span>
                )}
                <span className="flex items-center gap-1.5">
                  <ReachIcon className="w-4 h-4" aria-hidden="true" />
                  {reachMeta.label}
                </span>
              </div>

              {/* Actions */}
              <div className="flex flex-wrap gap-3 mt-4">
                {isAuthenticated && member.messaging_enabled && (
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
                    onPress={() =>
                      navigate(
                        tenantPath(`/federation/messages?compose=true&to_user=${member.id}&to_tenant=${member.timebank.id}`)
                      )
                    }
                  >
                    Send Message
                  </Button>
                )}
                <Button
                  variant="flat"
                  className="bg-theme-elevated text-theme-primary"
                  startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
                  onPress={() => navigate(tenantPath('/federation/members'))}
                >
                  Back to Members
                </Button>
              </div>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Bio */}
      {member.bio && (
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }}>
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-theme-primary mb-3 flex items-center gap-2">
              <User className="w-5 h-5 text-indigo-500" aria-hidden="true" />
              About
            </h2>
            <p className="text-theme-muted whitespace-pre-line">{member.bio}</p>
          </GlassCard>
        </motion.div>
      )}

      {/* Skills */}
      {skills.length > 0 && (
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.15 }}>
          <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-theme-primary mb-3">
              Skills & Interests
            </h2>
            <div className="flex flex-wrap gap-2">
              {skills.map((skill) => (
                <Chip
                  key={skill}
                  variant="flat"
                  className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                >
                  {skill}
                </Chip>
              ))}
            </div>
          </GlassCard>
        </motion.div>
      )}
    </div>
  );
}

export default FederationMemberProfilePage;
