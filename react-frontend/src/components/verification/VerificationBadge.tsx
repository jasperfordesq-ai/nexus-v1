// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * VerificationBadge - Displays verification badge icons on profiles
 *
 * Badge types: email_verified, phone_verified, id_verified,
 * dbs_checked, admin_verified.
 *
 * Shows as shield icons with tooltip on hover.
 */

import { useState, useEffect } from 'react';
import { Tooltip, Chip } from '@heroui/react';
import {
  ShieldCheck,
  Mail,
  Phone,
  Fingerprint,
  FileCheck,
  UserCheck,
  Shield,
} from 'lucide-react';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface VerificationBadgeData {
  type: string;
  label: string;
  description: string;
  verified: boolean;
  verified_at?: string;
  verified_by?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Badge Config
// ─────────────────────────────────────────────────────────────────────────────

const badgeConfig: Record<string, {
  icon: React.ReactNode;
  color: string;
  bgColor: string;
  label: string;
}> = {
  email_verified: {
    icon: <Mail className="w-3.5 h-3.5" />,
    color: 'text-blue-500',
    bgColor: 'bg-blue-500/10',
    label: 'Email Verified',
  },
  phone_verified: {
    icon: <Phone className="w-3.5 h-3.5" />,
    color: 'text-emerald-500',
    bgColor: 'bg-emerald-500/10',
    label: 'Phone Verified',
  },
  id_verified: {
    icon: <Fingerprint className="w-3.5 h-3.5" />,
    color: 'text-purple-500',
    bgColor: 'bg-purple-500/10',
    label: 'ID Verified',
  },
  dbs_checked: {
    icon: <FileCheck className="w-3.5 h-3.5" />,
    color: 'text-amber-500',
    bgColor: 'bg-amber-500/10',
    label: 'DBS Checked',
  },
  admin_verified: {
    icon: <UserCheck className="w-3.5 h-3.5" />,
    color: 'text-indigo-500',
    bgColor: 'bg-indigo-500/10',
    label: 'Admin Verified',
  },
};

// ─────────────────────────────────────────────────────────────────────────────
// Single Badge Icon
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
    color: 'text-theme-subtle',
    bgColor: 'bg-theme-elevated',
    label: badge.label || badge.type,
  };

  const sizeClasses = {
    sm: 'w-6 h-6',
    md: 'w-8 h-8',
    lg: 'w-10 h-10',
  };

  const tooltipContent = (
    <div className="text-center py-1 px-1">
      <p className="font-medium text-xs">{config.label}</p>
      {badge.description && (
        <p className="text-[10px] text-default-400 mt-0.5">{badge.description}</p>
      )}
      {badge.verified_at && (
        <p className="text-[10px] text-default-400 mt-0.5">
          Since {new Date(badge.verified_at).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}
        </p>
      )}
    </div>
  );

  return (
    <Tooltip content={tooltipContent} delay={200} closeDelay={0} size="sm">
      <div
        className={`${sizeClasses[size]} rounded-full ${config.bgColor} ${config.color} flex items-center justify-center`}
        aria-label={config.label}
        role="img"
      >
        {config.icon}
      </div>
    </Tooltip>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Badge Row (multiple badges inline)
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
          setBadges(response.data.filter((b) => b.verified));
        }
      } catch (err) {
        logError('Failed to load verification badges', err);
      } finally {
        setIsLoaded(true);
      }
    };
    loadBadges();
  }, [userId, propBadges]);

  if (!isLoaded || badges.length === 0) return null;

  return (
    <div className="flex items-center gap-1" aria-label="Verification badges">
      {badges.map((badge) => (
        <VerificationBadgeIcon key={badge.type} badge={badge} size={size} />
      ))}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Badge Summary Card (for profile page)
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
          setBadges(response.data);
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

  const verifiedBadges = badges.filter((b) => b.verified);
  if (verifiedBadges.length === 0) return null;

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-2">
        <Shield className="w-4 h-4 text-indigo-500" aria-hidden="true" />
        <span className="text-sm font-medium text-theme-primary">Verification</span>
      </div>
      <div className="flex flex-wrap gap-2">
        {verifiedBadges.map((badge) => {
          const config = badgeConfig[badge.type];
          return (
            <Chip
              key={badge.type}
              size="sm"
              variant="flat"
              className={`${config?.bgColor || 'bg-theme-elevated'} ${config?.color || 'text-theme-muted'}`}
              startContent={config?.icon || <ShieldCheck className="w-3 h-3" />}
            >
              {config?.label || badge.label}
            </Chip>
          );
        })}
      </div>
    </div>
  );
}

export default VerificationBadgeRow;
