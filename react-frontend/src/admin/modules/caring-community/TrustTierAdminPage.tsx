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
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Save from 'lucide-react/icons/save';
import ShieldCheck from 'lucide-react/icons/shield-check';
import { useToast } from '@/contexts';
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
      showToast('Trust tier configuration saved.', 'success');
      setLocalCriteria(null);
      void refetch();
    } catch {
      showToast('Failed to save configuration.', 'error');
    } finally {
      setSaving(false);
    }
  }

  async function handleRecompute() {
    if (!confirm('Recompute trust tiers for all active members? This may take a moment.')) return;
    setRecomputing(true);
    try {
      const result = await api.post<{ data: { updated: number } }>(
        '/v2/admin/caring-community/trust-tier/recompute',
        {},
      );
      const updated = result.data?.data?.updated ?? 0;
      showToast(`Tiers recomputed for ${updated} members.`, 'success');
    } catch {
      showToast('Failed to recompute tiers.', 'error');
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
              </tr>
            </thead>
            <tbody>
              {[
                { level: 0, name: 'Newcomer',    color: 'Grey',   desc: 'Just joined, no activity required' },
                { level: 1, name: 'Member',      color: 'Blue',   desc: 'Has logged hours' },
                { level: 2, name: 'Trusted',     color: 'Green',  desc: 'Active with reviews' },
                { level: 3, name: 'Verified',    color: 'Purple', desc: 'Identity verified' },
                { level: 4, name: 'Coordinator', color: 'Amber',  desc: 'Highly active, verified identity' },
              ].map((row) => (
                <tr key={row.level} className="border-b border-divider last:border-0">
                  <td className="px-4 py-3 font-mono text-xs">{row.level}</td>
                  <td className="px-4 py-3 font-medium">{row.name}</td>
                  <td className="px-4 py-3 text-foreground-500">{row.color}</td>
                  <td className="px-4 py-3 text-foreground-500">{row.desc}</td>
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
