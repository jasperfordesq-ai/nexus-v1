// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * VerificationBadge — Displays labeled verification badges across the platform.
 *
 * Used on: profiles, listings, members, exchanges, marketplace, messages, mobile menu.
 * Always shows labels — never just icons. Green = ID Verified (trust signal).
 */

import { useState, useEffect } from 'react';
import { Chip } from '@heroui/react';
import {
  ShieldCheck,
  ShieldOff,
  Mail,
  Phone,
  FileCheck,
  UserCheck,
  Shield,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface VerificationBadgeData {
  type: string;
  badge_type?: string;
  label: string;
  description?: string;
  verified?: boolean;
  verified_at?: string;
  granted_at?: string;
  verified_by?: string;
  verified_by_name?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Badge Config — GREEN for ID Verified (trust signal)
// ─────────────────────────────────────────────────────────────────────────────

const badgeConfig: Record<string, {
  icon: React.ReactNode;
  iconSm: React.ReactNode;
  color: string;
  bgColor: string;
  label: string;
}> = {
  email_verified: {
    icon: <Mail className="w-3.5 h-3.5" />,
    iconSm: <Mail className="w-3 h-3" />,
    color: 'text-blue-600 dark:text-blue-400',
    bgColor: 'bg-blue-500/10',
    label: 'Email Verified',
  },
  phone_verified: {
    icon: <Phone className="w-3.5 h-3.5" />,
    iconSm: <Phone className="w-3 h-3" />,
    color: 'text-emerald-600 dark:text-emerald-400',
    bgColor: 'bg-emerald-500/10',
    label: 'Phone Verified',
  },
  id_verified: {
    icon: <ShieldCheck className="w-3.5 h-3.5" />,
    iconSm: <ShieldCheck className="w-3 h-3" />,
    color: 'text-emerald-700 dark:text-emerald-300',
    bgColor: 'bg-emerald-500/15 dark:bg-emerald-500/20',
    label: 'ID Verified',
  },
  dbs_checked: {
    icon: <FileCheck className="w-3.5 h-3.5" />,
    iconSm: <FileCheck className="w-3 h-3" />,
    color: 'text-amber-600 dark:text-amber-400',
    bgColor: 'bg-amber-500/10',
    label: 'DBS Checked',
  },
  admin_verified: {
    icon: <UserCheck className="w-3.5 h-3.5" />,
    iconSm: <UserCheck className="w-3 h-3" />,
    color: 'text-indigo-600 dark:text-indigo-400',
    bgColor: 'bg-indigo-500/10',
    label: 'Admin Verified',
  },
};

// ─────────────────────────────────────────────────────────────────────────────
// Helpers — normalize API response
// ─────────────────────────────────────────────────────────────────────────────

function normalizeBadges(data: VerificationBadgeData[]): VerificationBadgeData[] {
  return data.map((b) => ({
    ...b,
    type: b.type || b.badge_type || '',
    verified_at: b.verified_at || b.granted_at,
  }));
}

// ─────────────────────────────────────────────────────────────────────────────
// Single Badge Icon (for tooltip-only compact spaces)
// ─────────────────────────────────────────────────────────────────────────────

export function VerificationBadgeIcon({
  badge,
  size = 'sm',
}: {
  badge: VerificationBadgeData;
  size?: 'sm' | 'md' | 'lg';
}) {
  const config = badgeConfig[badge.type] || {
    icon: <ShieldCheck className="w-3.5 h-3.5" />,
    iconSm: <ShieldCheck className="w-3 h-3" />,
    color: 'text-theme-subtle',
    bgColor: 'bg-theme-elevated',
    label: badge.label || badge.type,
  };

  const sizeClasses = {
    sm: 'w-6 h-6',
    md: 'w-8 h-8',
    lg: 'w-10 h-10',
  };

  return (
    <div
      className={`${sizeClasses[size]} rounded-full ${config.bgColor} ${config.color} flex items-center justify-center`}
      aria-label={config.label}
      role="img"
      title={config.label}
    >
      {config.icon}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Badge Row — LABELED chips (primary display component used everywhere)
// ─────────────────────────────────────────────────────────────────────────────

export function VerificationBadgeRow({
  userId,
  badges: propBadges,
  size = 'sm',
}: {
  userId?: number;
  badges?: VerificationBadgeData[];
  size?: 'sm' | 'md' | 'lg';
}) {
  const { t } = useTranslation('common');
  const [badges, setBadges] = useState<VerificationBadgeData[]>(propBadges || []);
  const [isLoaded, setIsLoaded] = useState(!!propBadges);

  useEffect(() => {
    if (propBadges) {
      setBadges(propBadges);
      setIsLoaded(true);
      return;
    }

    if (!userId) return;

    const loadBadges = async () => {
      try {
        const response = await api.get<VerificationBadgeData[]>(`/v2/users/${userId}/verification-badges`);
        if (response.success && response.data) {
          setBadges(normalizeBadges(Array.isArray(response.data) ? response.data : []));
        }
      } catch (err) {
        logError('Failed to load verification badges', err);
      } finally {
        setIsLoaded(true);
      }
    };
    loadBadges();
  }, [userId, propBadges]);

  if (!isLoaded) return null;

  const hasIdVerified = badges.some((b) => b.type === 'id_verified');
  const unverifiedConfig = {
    icon: <ShieldOff className="w-3.5 h-3.5" />,
    iconSm: <ShieldOff className="w-3 h-3" />,
    color: 'text-theme-muted',
    bgColor: 'bg-theme-elevated',
    label: t('verification.not_id_verified', 'Not ID Verified'),
  };

  const allBadges = hasIdVerified ? badges : [
    ...badges,
    { type: '__unverified__', label: unverifiedConfig.label },
  ];

  return (
    <div className="flex items-center gap-1.5 flex-wrap" aria-label={t('aria.verification_badges', 'Verification badges')}>
      {allBadges.map((badge) => {
        const isUnverified = badge.type === '__unverified__';
        const config = isUnverified ? unverifiedConfig : (badgeConfig[badge.type] || {
          icon: <ShieldCheck className="w-3.5 h-3.5" />,
          iconSm: <ShieldCheck className="w-3 h-3" />,
          color: 'text-theme-subtle',
          bgColor: 'bg-theme-elevated',
          label: badge.label || badge.type,
        });

        if (size === 'sm') {
          return (
            <span
              key={badge.type}
              className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ${config.bgColor} ${config.color}`}
            >
              {config.iconSm}
              {config.label}
            </span>
          );
        }

        // Medium/large: bigger chip for profile and detail pages
        return (
          <Chip
            key={badge.type}
            size="md"
            variant="flat"
            className={`${config.bgColor} ${config.color} font-semibold`}
            startContent={config.icon}
          >
            {config.label}
          </Chip>
        );
      })}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Badge Summary Card (for profile sidebar/details section)
// ─────────────────────────────────────────────────────────────────────────────

export function VerificationBadgeSummary({
  userId,
}: {
  userId: number;
}) {
  const [badges, setBadges] = useState<VerificationBadgeData[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const loadBadges = async () => {
      try {
        const response = await api.get<VerificationBadgeData[]>(`/v2/users/${userId}/verification-badges`);
        if (response.success && response.data) {
          setBadges(normalizeBadges(Array.isArray(response.data) ? response.data : []));
        }
      } catch (err) {
        logError('Failed to load verification badges', err);
      } finally {
        setIsLoading(false);
      }
    };
    loadBadges();
  }, [userId]);

  if (isLoading) return null;

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-2">
        <Shield className="w-4 h-4 text-emerald-500" aria-hidden="true" />
        <span className="text-sm font-semibold text-theme-primary">Verification Status</span>
      </div>
      {badges.length === 0 ? (
        <div className="flex items-center gap-2 px-3 py-2 rounded-lg bg-theme-elevated">
          <ShieldOff className="w-4 h-4 text-theme-muted shrink-0" aria-hidden="true" />
          <span className="text-xs text-theme-muted">Identity not verified</span>
        </div>
      ) : (
        <div className="flex flex-wrap gap-2">
          {badges.map((badge) => {
            const config = badgeConfig[badge.type];
            return (
              <Chip
                key={badge.type}
                size="md"
                variant="flat"
                className={`${config?.bgColor || 'bg-theme-elevated'} ${config?.color || 'text-theme-muted'} font-semibold`}
                startContent={config?.icon || <ShieldCheck className="w-3.5 h-3.5" />}
              >
                {config?.label || badge.label}
              </Chip>
            );
          })}
        </div>
      )}
    </div>
  );
}

export default VerificationBadgeRow;
