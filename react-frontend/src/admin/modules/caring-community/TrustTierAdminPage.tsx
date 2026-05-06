// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Divider,
  Input,
  Spinner,
  Switch,
} from '@heroui/react';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Save from 'lucide-react/icons/save';
import ShieldCheck from 'lucide-react/icons/shield-check';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { useApi } from '@/hooks/useApi';
import api from '@/lib/api';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface TierCriteria {
  hours_logged: number;
  reviews_received: number;
  identity_verified: boolean;
}

type TierName = 'member' | 'trusted' | 'verified' | 'coordinator';

interface TierConfigResponse {
  criteria: Record<TierName, TierCriteria>;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const TIER_DISPLAY: Record<TierName, { label: string; description: string }> = {
  member:      { label: 'Member',      description: 'Basic community member with at least 1 hour logged' },
  trusted:     { label: 'Trusted',     description: 'Active member with several hours and reviews' },
  verified:    { label: 'Verified',    description: 'Trusted member with confirmed identity' },
  coordinator: { label: 'Coordinator', description: 'Highly active verified member eligible to coordinate' },
};

const TIER_ORDER: TierName[] = ['member', 'trusted', 'verified', 'coordinator'];

// ---------------------------------------------------------------------------
// Sub-component
// ---------------------------------------------------------------------------

interface TierRowProps {
  tierName: TierName;
  criteria: TierCriteria;
  onChange: (tierName: TierName, field: keyof TierCriteria, value: number | boolean) => void;
}

function TierRow({ tierName, criteria, onChange }: TierRowProps) {
  const { label, description } = TIER_DISPLAY[tierName];

  return (
    <div className="space-y-3">
      <div>
        <p className="font-semibold text-sm">{label}</p>
        <p className="text-xs text-foreground-500">{description}</p>
      </div>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Input
          type="number"
          label="Hours logged (min)"
          value={String(criteria.hours_logged)}
          min={0}
          onValueChange={(v) => onChange(tierName, 'hours_logged', Math.max(0, parseInt(v || '0', 10)))}
          variant="bordered"
          size="sm"
        />
        <Input
          type="number"
          label="Reviews received (min)"
          value={String(criteria.reviews_received)}
          min={0}
          onValueChange={(v) => onChange(tierName, 'reviews_received', Math.max(0, parseInt(v || '0', 10)))}
          variant="bordered"
          size="sm"
        />
        <div className="flex items-center gap-2 pt-2">
          <Switch
            isSelected={criteria.identity_verified}
            onValueChange={(checked) => onChange(tierName, 'identity_verified', checked)}
            size="sm"
            aria-label="Require identity verified"
          />
          <span className="text-sm">Require identity verified</span>
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export function TrustTierAdminPage() {
  const { t } = useTranslation('caring_community');
  usePageTitle(t('panel.sidebar.items.trust_tier'));
  const { showToast } = useToast();

  const { data, isLoading, error, refetch } = useApi<TierConfigResponse>(
    '/v2/admin/caring-community/trust-tier/config',
    { immediate: true },
  );

  const [localCriteria, setLocalCriteria] = useState<Record<TierName, TierCriteria> | null>(null);
  const [saving, setSaving] = useState(false);
  const [recomputing, setRecomputing] = useState(false);

  // Use local edits if present, otherwise fall back to server data
  const criteria = localCriteria ?? data?.criteria ?? null;

  function handleChange(tierName: TierName, field: keyof TierCriteria, value: number | boolean) {
    setLocalCriteria((prev) => {
      const base = prev ?? (data?.criteria ?? {} as Record<TierName, TierCriteria>);
      return {
        ...base,
        [tierName]: {
          ...base[tierName],
          [field]: value,
        },
      };
    });
  }

  async function handleSave() {
    if (!criteria) return;
    setSaving(true);
    try {
      await api.put('/v2/admin/caring-community/trust-tier/config', { criteria });
      showToast(t('admin.trust_tier.messages.saved'), 'success');
      setLocalCriteria(null);
      void refetch();
    } catch {
      showToast(t('admin.trust_tier.errors.save_failed'), 'error');
    } finally {
      setSaving(false);
    }
  }

  async function handleRecompute() {
    if (!window.confirm(t('admin.trust_tier.recompute_confirm'))) return;
    setRecomputing(true);
    try {
      const result = await api.post<{ updated: number }>(
        '/v2/admin/caring-community/trust-tier/recompute',
        {},
      );
      const updated = result.data?.updated ?? 0;
      showToast(t('admin.trust_tier.messages.recomputed', { count: updated }), 'success');
    } catch {
      showToast(t('admin.trust_tier.errors.recompute_failed'), 'error');
    } finally {
      setRecomputing(false);
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          <ShieldCheck className="h-6 w-6 text-primary" aria-hidden="true" />
          <div>
            <h1 className="text-xl font-bold">Trust Tier Configuration</h1>
            <p className="text-sm text-foreground-500">
              Set criteria thresholds for each trust level. Changes apply on next recompute.
            </p>
          </div>
        </div>
        <div className="flex gap-2">
          <div className="flex flex-col items-end gap-1">
            <Button
              color="default"
              variant="bordered"
              size="sm"
              startContent={<RefreshCw className="h-4 w-4" aria-hidden="true" />}
              onPress={() => void handleRecompute()}
              isLoading={recomputing}
              isDisabled={recomputing || saving}
            >
              Recompute All Tiers
            </Button>
            <p className="text-xs text-foreground-400 max-w-xs text-right">
              Use Recompute when you change thresholds and want existing members re-evaluated immediately.
              Runs in the background — may take 30–60 seconds.
            </p>
          </div>
          <Button
            color="primary"
            size="sm"
            startContent={<Save className="h-4 w-4" aria-hidden="true" />}
            onPress={() => void handleSave()}
            isLoading={saving}
            isDisabled={saving || recomputing || !localCriteria}
          >
            Save Configuration
          </Button>
        </div>
      </div>

      {/* About card */}
      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                Trust Tiers are a reputation ladder that unlocks privileges as members become more active and verified.
                Members advance automatically when they meet the criteria thresholds below — no manual action required
                unless you run "Recompute All Tiers" to force an immediate recalculation for all members. Tier
                progression affects the Warmth Pass (Trusted tier and above), coordinator eligibility (Coordinator
                tier), and visibility in the member directory.
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Loading */}
      {isLoading && (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      )}

      {/* Error */}
      {!isLoading && error && (
        <Card>
          <CardBody>
            <p className="text-danger text-sm">Failed to load trust tier configuration.</p>
          </CardBody>
        </Card>
      )}

      {/* Tier criteria cards */}
      {!isLoading && !error && criteria && (
        <Card>
          <CardHeader>
            <p className="font-semibold text-sm">Tier Criteria Thresholds</p>
          </CardHeader>
          <Divider />
          <CardBody className="space-y-6 p-6">
            {TIER_ORDER.map((tierName, i) => (
              <div key={tierName}>
                <TierRow
                  tierName={tierName}
                  criteria={criteria[tierName]}
                  onChange={handleChange}
                />
                {i < TIER_ORDER.length - 1 && <Divider className="mt-6" />}
              </div>
            ))}
          </CardBody>
        </Card>
      )}

      {/* Tier reference table */}
      <Card>
        <CardHeader>
          <p className="font-semibold text-sm">Tier Reference</p>
        </CardHeader>
        <Divider />
        <CardBody className="p-0">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-divider bg-content2">
                <th className="px-4 py-3 text-left font-semibold">Tier</th>
                <th className="px-4 py-3 text-left font-semibold">Level</th>
                <th className="px-4 py-3 text-left font-semibold">Color</th>
                <th className="px-4 py-3 text-left font-semibold">Description</th>
                <th className="px-4 py-3 text-left font-semibold">What it unlocks</th>
              </tr>
            </thead>
            <tbody>
              {[
                { level: 0, name: 'Newcomer',    color: 'Grey',   desc: 'Just joined, no activity required',      unlocks: 'Basic member access' },
                { level: 1, name: 'Member',      color: 'Blue',   desc: 'Has logged hours',                        unlocks: 'Can post help listings, receive care hours' },
                { level: 2, name: 'Trusted',     color: 'Green',  desc: 'Active with reviews',                     unlocks: 'Eligible for Warmth Pass, can be recommended for care' },
                { level: 3, name: 'Verified',    color: 'Purple', desc: 'Identity verified',                       unlocks: 'Full care coordinator visibility, identity badge' },
                { level: 4, name: 'Coordinator', color: 'Amber',  desc: 'Highly active, verified identity',        unlocks: 'Eligible to approve hours and coordinate matches' },
              ].map((row) => (
                <tr key={row.level} className="border-b border-divider last:border-0">
                  <td className="px-4 py-3 font-mono text-xs">{row.level}</td>
                  <td className="px-4 py-3 font-medium">{row.name}</td>
                  <td className="px-4 py-3 text-foreground-500">{row.color}</td>
                  <td className="px-4 py-3 text-foreground-500">{row.desc}</td>
                  <td className="px-4 py-3 text-foreground-500">{row.unlocks}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </CardBody>
      </Card>
    </div>
  );
}

export default TrustTierAdminPage;
