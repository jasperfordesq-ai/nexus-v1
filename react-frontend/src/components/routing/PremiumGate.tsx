// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PremiumGate — AG58
 *
 * Wraps content that requires a member premium feature unlock.
 * - If the tenant doesn't have the `member_premium` feature enabled at all,
 *   children render as-is (gating is a no-op for tenants not using premium).
 * - If the user is not authenticated or hasn't unlocked the requested feature
 *   key via their tier, an upgrade CTA is shown instead of children.
 *
 * Fetches /v2/member-premium/me once per page load, cached in module memory.
 */

import { type ReactNode, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, Button } from '@heroui/react';
import Crown from 'lucide-react/icons/crown';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant } from '@/contexts';
import api from '@/lib/api';

interface MeResponse {
  subscription: { is_active: boolean } | null;
  entitled_tier: { tier_id: number; tier_name: string; features: string[] } | null;
  unlocked_features: string[];
}

let cachedFeatures: string[] | null = null;
let inflight: Promise<string[]> | null = null;

async function fetchUnlocked(): Promise<string[]> {
  if (cachedFeatures !== null) return cachedFeatures;
  if (inflight) return inflight;
  inflight = (async () => {
    try {
      const res = await api.get<MeResponse>('/v2/member-premium/me');
      cachedFeatures = res.data?.unlocked_features ?? [];
      return cachedFeatures ?? [];
    } catch {
      cachedFeatures = [];
      return cachedFeatures ?? [];
    } finally {
      inflight = null;
    }
  })();
  return inflight;
}

/** Reset the cache (e.g., after subscribe/cancel). */
export function invalidatePremiumCache(): void {
  cachedFeatures = null;
}

interface PremiumGateProps {
  /** Feature key the user must have unlocked (e.g. "verified_badge"). */
  featureKey: string;
  /** Content shown when the feature is unlocked. */
  children: ReactNode;
  /** Optional override for the upgrade UI shown when locked. */
  fallback?: ReactNode;
  /** When true, render nothing instead of the upgrade CTA. */
  silent?: boolean;
}

export function PremiumGate({ featureKey, children, fallback, silent = false }: PremiumGateProps) {
  const { t } = useTranslation('common');
  const { isAuthenticated } = useAuth();
  const { hasFeature, tenantPath } = useTenant();
  const [unlocked, setUnlocked] = useState<string[] | null>(cachedFeatures);

  const tenantHasPremium = hasFeature('member_premium');

  useEffect(() => {
    if (!tenantHasPremium || !isAuthenticated) return;
    let cancelled = false;
    fetchUnlocked().then((arr) => {
      if (!cancelled) setUnlocked(arr);
    });
    return () => {
      cancelled = true;
    };
  }, [tenantHasPremium, isAuthenticated]);

  // No premium configured — render children unchanged.
  if (!tenantHasPremium) {
    return <>{children}</>;
  }

  const isUnlocked = !!isAuthenticated && (unlocked?.includes(featureKey) ?? false);

  if (isUnlocked) {
    return <>{children}</>;
  }

  if (silent) return null;
  if (fallback !== undefined) return <>{fallback}</>;

  return (
    <Card shadow="sm">
      <CardBody className="text-center py-8 flex flex-col items-center gap-3">
        <Crown size={36} className="text-yellow-500" />
        <h3 className="text-lg font-semibold">
          {t('premium.gate_title', 'Premium feature')}
        </h3>
        <p className="text-sm text-[var(--color-text-secondary)] max-w-md">
          {t('premium.gate_body', 'Subscribe to a premium tier to unlock this feature.')}
        </p>
        <Button as={Link} to={tenantPath('/premium')} color="primary">
          {t('premium.gate_cta', 'View Premium Tiers')}
        </Button>
      </CardBody>
    </Card>
  );
}

export default PremiumGate;
