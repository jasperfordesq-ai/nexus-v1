// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Matching Configuration Page
 * Admin form for configuring Smart Matching algorithm weights,
 * proximity bands, and algorithm toggles.
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Input,
  Button,
  Slider,
  Divider,
  Switch,
  Spinner,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
} from '@heroui/react';
import { ArrowLeft, Save, Trash2, RotateCcw } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminMatching } from '../../api/adminApi';
import { PageHeader, ConfirmModal } from '../../components';
import type { SmartMatchingConfig } from '../../api/types';

/** Proximity band label mapping */
const BAND_LABELS = ['Walking', 'Local', 'City', 'Regional', 'Max'];

/** Default config for reset */
const DEFAULT_CONFIG: SmartMatchingConfig = {
  category_weight: 0.25,
  skill_weight: 0.20,
  proximity_weight: 0.25,
  freshness_weight: 0.10,
  reciprocity_weight: 0.15,
  quality_weight: 0.05,
  proximity_bands: [
    { distance_km: 5, score: 1.0 },
    { distance_km: 15, score: 0.9 },
    { distance_km: 30, score: 0.7 },
    { distance_km: 50, score: 0.5 },
    { distance_km: 100, score: 0.2 },
  ],
  enabled: true,
  broker_approval_enabled: true,
  max_distance_km: 50,
  min_match_score: 40,
  hot_match_threshold: 80,
};

export function MatchingConfig() {
  usePageTitle('Admin - Matching Configuration');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [config, setConfig] = useState<SmartMatchingConfig>(DEFAULT_CONFIG);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [dirty, setDirty] = useState(false);
  const [clearModalOpen, setClearModalOpen] = useState(false);
  const [clearing, setClearing] = useState(false);
  const [resetModalOpen, setResetModalOpen] = useState(false);

  const loadConfig = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminMatching.getConfig();
      if (res.success && res.data) {
        setConfig(res.data);
        setDirty(false);
      }
    } catch {
      toast.error('Failed to load matching configuration');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadConfig();
  }, [loadConfig]);

  /** Update a weight value */
  const updateWeight = useCallback(
    (key: keyof SmartMatchingConfig, value: number) => {
      setConfig((prev) => ({ ...prev, [key]: value }));
      setDirty(true);
    },
    []
  );

  /** Update a proximity band */
  const updateBand = useCallback(
    (index: number, field: 'distance_km' | 'score', value: number) => {
      setConfig((prev) => {
        const bands = [...(prev.proximity_bands || [])];
        bands[index] = { ...bands[index], [field]: value };
        return { ...prev, proximity_bands: bands };
      });
      setDirty(true);
    },
    []
  );

  /** Total of all weights */
  const totalWeight = useMemo(() => {
    return (
      (config.category_weight ?? 0) +
      (config.skill_weight ?? 0) +
      (config.proximity_weight ?? 0) +
      (config.freshness_weight ?? 0) +
      (config.reciprocity_weight ?? 0) +
      (config.quality_weight ?? 0)
    );
  }, [config]);

  const totalPct = Math.round(totalWeight * 100);
  const totalValid = totalPct >= 95 && totalPct <= 105;

  /** Save config to API */
  const handleSave = useCallback(async () => {
    if (!totalValid) {
      toast.error(`Weights must sum to approximately 100% (currently ${totalPct}%)`);
      return;
    }

    setSaving(true);
    try {
      const res = await adminMatching.updateConfig(config);
      if (res.success) {
        toast.success('Matching configuration saved successfully');
        setDirty(false);
      } else {
        toast.error('Failed to save configuration');
      }
    } catch {
      toast.error('Failed to save configuration');
    } finally {
      setSaving(false);
    }
  }, [config, totalValid, totalPct, toast]);

  /** Clear cache */
  const handleClearCache = useCallback(async () => {
    setClearing(true);
    try {
      const res = await adminMatching.clearCache();
      if (res.success) {
        const cleared = (res.data as { entries_cleared?: number })?.entries_cleared ?? 0;
        toast.success(`Match cache cleared (${cleared} entries removed)`);
        setClearModalOpen(false);
      } else {
        toast.error('Failed to clear cache');
      }
    } catch {
      toast.error('Failed to clear cache');
    } finally {
      setClearing(false);
    }
  }, [toast]);

  /** Reset to defaults */
  const handleReset = useCallback(() => {
    setConfig(DEFAULT_CONFIG);
    setDirty(true);
    setResetModalOpen(false);
    toast.info('Configuration reset to defaults (save to apply)');
  }, [toast]);

  if (loading) {
    return (
      <div>
        <PageHeader
          title="Matching Configuration"
          description="Configure the smart matching algorithm"
        />
        <div className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Matching Configuration"
        description="Configure the smart matching algorithm weights and proximity bands"
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/smart-matching'))}
              size="sm"
            >
              Back
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
              isDisabled={!dirty}
              size="sm"
            >
              Save Changes
            </Button>
          </div>
        }
      />

      <div className="space-y-6">
        {/* Algorithm Toggles */}
        <Card shadow="sm">
          <CardHeader className="px-4 pt-4 pb-0">
            <h3 className="font-semibold">Algorithm Settings</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium">Smart Matching Enabled</p>
                  <p className="text-xs text-default-500">
                    Enable or disable the matching algorithm for this community
                  </p>
                </div>
                <Switch
                  isSelected={config.enabled ?? true}
                  onValueChange={(val) => {
                    setConfig((prev) => ({ ...prev, enabled: val }));
                    setDirty(true);
                  }}
                  aria-label="Toggle smart matching"
                />
              </div>
              <Divider />
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium">Broker Approval Required</p>
                  <p className="text-xs text-default-500">
                    Require broker review before matches are shown to users
                  </p>
                </div>
                <Switch
                  isSelected={config.broker_approval_enabled ?? true}
                  onValueChange={(val) => {
                    setConfig((prev) => ({ ...prev, broker_approval_enabled: val }));
                    setDirty(true);
                  }}
                  aria-label="Toggle broker approval"
                />
              </div>
              <Divider />
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Input
                  type="number"
                  label="Max Distance (km)"
                  value={String(config.max_distance_km ?? 50)}
                  onValueChange={(val) => {
                    setConfig((prev) => ({ ...prev, max_distance_km: parseInt(val) || 50 }));
                    setDirty(true);
                  }}
                  variant="bordered"
                  size="sm"
                />
                <Input
                  type="number"
                  label="Min Match Score"
                  value={String(config.min_match_score ?? 40)}
                  onValueChange={(val) => {
                    setConfig((prev) => ({ ...prev, min_match_score: parseInt(val) || 40 }));
                    setDirty(true);
                  }}
                  variant="bordered"
                  size="sm"
                />
                <Input
                  type="number"
                  label="Hot Match Threshold"
                  value={String(config.hot_match_threshold ?? 80)}
                  onValueChange={(val) => {
                    setConfig((prev) => ({ ...prev, hot_match_threshold: parseInt(val) || 80 }));
                    setDirty(true);
                  }}
                  variant="bordered"
                  size="sm"
                />
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Algorithm Weights */}
        <Card shadow="sm">
          <CardHeader className="flex items-center justify-between px-4 pt-4 pb-0">
            <h3 className="font-semibold">Algorithm Weights</h3>
            <span
              className={`text-sm font-medium ${
                totalValid ? 'text-success' : 'text-danger'
              }`}
            >
              Total: {totalPct}%
              {!totalValid && ' (should be ~100%)'}
            </span>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            <div className="space-y-6">
              {/* Category Weight */}
              <WeightSlider
                label="Category Weight"
                description="Weight for category match alignment"
                value={config.category_weight}
                onChange={(v) => updateWeight('category_weight', v)}
              />
              {/* Skill Weight */}
              <WeightSlider
                label="Skill Weight"
                description="Weight for skill complementarity"
                value={config.skill_weight}
                onChange={(v) => updateWeight('skill_weight', v)}
              />
              {/* Proximity Weight */}
              <WeightSlider
                label="Proximity Weight"
                description="Weight for geographic proximity"
                value={config.proximity_weight}
                onChange={(v) => updateWeight('proximity_weight', v)}
              />
              {/* Freshness Weight */}
              <WeightSlider
                label="Freshness Weight"
                description="Weight for listing recency"
                value={config.freshness_weight}
                onChange={(v) => updateWeight('freshness_weight', v)}
              />
              {/* Reciprocity Weight */}
              <WeightSlider
                label="Reciprocity Weight"
                description="Weight for mutual exchange potential"
                value={config.reciprocity_weight}
                onChange={(v) => updateWeight('reciprocity_weight', v)}
              />
              {/* Quality Weight */}
              <WeightSlider
                label="Quality Weight"
                description="Weight for profile completeness and ratings"
                value={config.quality_weight}
                onChange={(v) => updateWeight('quality_weight', v)}
              />
            </div>
          </CardBody>
        </Card>

        {/* Proximity Bands */}
        <Card shadow="sm">
          <CardHeader className="px-4 pt-4 pb-0">
            <h3 className="font-semibold">Proximity Bands</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            <p className="text-sm text-default-500 mb-4">
              Configure distance thresholds and their corresponding score multipliers.
              Closer distances receive higher scores.
            </p>
            <Table
              aria-label="Proximity bands configuration"
              removeWrapper
              isCompact
            >
              <TableHeader>
                <TableColumn>BAND</TableColumn>
                <TableColumn>DISTANCE (km)</TableColumn>
                <TableColumn>SCORE</TableColumn>
              </TableHeader>
              <TableBody>
                {(config.proximity_bands || []).map((band, i) => (
                  <TableRow key={i}>
                    <TableCell>
                      <span className="text-sm font-medium">
                        {BAND_LABELS[i] ?? `Band ${i + 1}`}
                      </span>
                    </TableCell>
                    <TableCell>
                      <Input
                        type="number"
                        value={String(band.distance_km)}
                        onValueChange={(val) =>
                          updateBand(i, 'distance_km', parseInt(val) || 0)
                        }
                        variant="bordered"
                        size="sm"
                        className="w-24"
                        aria-label={`${BAND_LABELS[i] ?? `Band ${i + 1}`} distance`}
                      />
                    </TableCell>
                    <TableCell>
                      <Input
                        type="number"
                        value={String(band.score)}
                        onValueChange={(val) =>
                          updateBand(i, 'score', parseFloat(val) || 0)
                        }
                        variant="bordered"
                        size="sm"
                        className="w-24"
                        step={0.1}
                        min={0}
                        max={1}
                        aria-label={`${BAND_LABELS[i] ?? `Band ${i + 1}`} score`}
                      />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardBody>
        </Card>

        {/* Cache Management & Actions */}
        <Card shadow="sm">
          <CardHeader className="px-4 pt-4 pb-0">
            <h3 className="font-semibold">Cache Management</h3>
          </CardHeader>
          <CardBody className="px-4 pb-4">
            <div className="flex flex-wrap gap-3">
              <Button
                color="danger"
                variant="flat"
                startContent={<Trash2 size={16} />}
                onPress={() => setClearModalOpen(true)}
              >
                Clear Match Cache
              </Button>
              <Button
                color="warning"
                variant="flat"
                startContent={<RotateCcw size={16} />}
                onPress={() => setResetModalOpen(true)}
              >
                Reset to Defaults
              </Button>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Clear Cache Modal */}
      <ConfirmModal
        isOpen={clearModalOpen}
        onClose={() => setClearModalOpen(false)}
        onConfirm={handleClearCache}
        title="Clear Match Cache"
        message="This will remove all cached matches for this tenant. New matches will be recalculated on next request. This action cannot be undone."
        confirmLabel="Clear Cache"
        confirmColor="danger"
        isLoading={clearing}
      />

      {/* Reset Defaults Modal */}
      <ConfirmModal
        isOpen={resetModalOpen}
        onClose={() => setResetModalOpen(false)}
        onConfirm={handleReset}
        title="Reset to Defaults"
        message="This will reset all algorithm weights and settings to their default values. You will still need to save to apply the changes."
        confirmLabel="Reset"
        confirmColor="warning"
      />
    </div>
  );
}

/** Reusable weight slider component */
function WeightSlider({
  label,
  description,
  value,
  onChange,
}: {
  label: string;
  description: string;
  value: number;
  onChange: (value: number) => void;
}) {
  const pct = Math.round(value * 100);

  return (
    <div>
      <div className="flex items-center justify-between mb-1">
        <div>
          <p className="text-sm font-medium">{label}</p>
          <p className="text-xs text-default-500">{description}</p>
        </div>
        <span className="text-sm font-semibold tabular-nums w-12 text-right">
          {pct}%
        </span>
      </div>
      <Slider
        step={0.05}
        minValue={0}
        maxValue={1}
        value={value}
        onChange={(val) => onChange(val as number)}
        aria-label={label}
        size="sm"
        className="mt-1"
      />
    </div>
  );
}

export default MatchingConfig;
