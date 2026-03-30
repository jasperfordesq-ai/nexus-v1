// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Badge Configuration — Admin page for per-tenant badge enable/disable and customization.
 */

import { useState, useCallback, useEffect } from 'react';
import { Card, CardBody, Switch, Chip, Button, Spinner, Tabs, Tab } from '@heroui/react';
import { Award, RotateCcw, Shield, Star, Gem, Zap } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminGamification } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { BadgeConfigEntry } from '../../api/types';

const TIER_LABELS: Record<string, string> = {
  core: 'Core',
  template: 'Template',
  custom: 'Custom',
};

const TIER_COLORS: Record<string, 'primary' | 'success' | 'warning' | 'secondary' | 'danger'> = {
  core: 'primary',
  template: 'success',
  custom: 'warning',
};

const RARITY_COLORS: Record<string, 'default' | 'primary' | 'success' | 'warning' | 'danger' | 'secondary'> = {
  common: 'default',
  uncommon: 'success',
  rare: 'primary',
  epic: 'warning',
  legendary: 'danger',
};

const CLASS_ICONS: Record<string, typeof Award> = {
  quantity: Star,
  quality: Gem,
  special: Zap,
  verification: Shield,
};

type FilterTab = 'all' | 'core' | 'template' | 'custom' | 'quality';

export function BadgeConfiguration() {
  usePageTitle('Badge Configuration');
  const toast = useToast();

  const [badges, setBadges] = useState<BadgeConfigEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [updating, setUpdating] = useState<string | null>(null);
  const [filter, setFilter] = useState<FilterTab>('all');

  const loadBadges = useCallback(async () => {
    setLoading(true);
    const res = await adminGamification.getBadgeConfig();
    if (res.success && res.data) {
      setBadges(res.data as BadgeConfigEntry[]);
    } else {
      toast.error('Failed to load badge configuration');
    }
    setLoading(false);
  }, [toast]);

  useEffect(() => {
    loadBadges();
  }, [loadBadges]);

  const handleToggle = async (badge: BadgeConfigEntry, enabled: boolean) => {
    if (badge.badge_tier === 'core') return;
    setUpdating(badge.key);
    const res = await adminGamification.updateBadgeConfig(badge.key, { is_enabled: enabled });
    if (res.success) {
      setBadges((prev) =>
        prev.map((b) => (b.key === badge.key ? { ...b, is_enabled: enabled } : b)),
      );
      toast.success(`${badge.name} ${enabled ? 'enabled' : 'disabled'}`);
    } else {
      toast.error('Failed to update badge');
    }
    setUpdating(null);
  };

  const handleReset = async (badge: BadgeConfigEntry) => {
    setUpdating(badge.key);
    const res = await adminGamification.resetBadgeConfig(badge.key);
    if (res.success) {
      toast.success(`${badge.name} reset to defaults`);
      loadBadges();
    } else {
      toast.error('Failed to reset badge');
    }
    setUpdating(null);
  };

  const filtered = badges.filter((b) => {
    if (filter === 'all') return true;
    if (filter === 'quality') return b.badge_class === 'quality';
    return b.badge_tier === filter;
  });

  const grouped = filtered.reduce<Record<string, BadgeConfigEntry[]>>((acc, b) => {
    const tier = b.badge_tier;
    if (!acc[tier]) acc[tier] = [];
    acc[tier].push(b);
    return acc;
  }, {});

  const tierOrder = ['core', 'template', 'custom'];

  if (loading) {
    return (
      <div>
        <PageHeader title="Badge Configuration" description="Configure which badges are available for your community" />
        <div className="flex items-center justify-center py-24">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title="Badge Configuration"
        description="Configure which badges are available for your community"
      />

      <Tabs
        aria-label="Badge filter"
        selectedKey={filter}
        onSelectionChange={(key) => setFilter(key as FilterTab)}
        className="mb-6"
      >
        <Tab key="all" title={`All (${badges.length})`} />
        <Tab key="core" title={`Core (${badges.filter((b) => b.badge_tier === 'core').length})`} />
        <Tab key="template" title={`Template (${badges.filter((b) => b.badge_tier === 'template').length})`} />
        <Tab key="custom" title={`Custom (${badges.filter((b) => b.badge_tier === 'custom').length})`} />
        <Tab key="quality" title={`Quality (${badges.filter((b) => b.badge_class === 'quality').length})`} />
      </Tabs>

      {filtered.length === 0 ? (
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center justify-center py-12">
            <Award size={40} className="text-default-300 mb-2" />
            <p className="text-default-500">No badges found for this filter</p>
          </CardBody>
        </Card>
      ) : (
        tierOrder
          .filter((tier) => grouped[tier]?.length)
          .map((tier) => (
            <div key={tier} className="mb-8">
              <h2 className="text-lg font-semibold text-foreground mb-3 flex items-center gap-2">
                <Chip color={TIER_COLORS[tier] ?? 'primary'} size="sm" variant="flat">
                  {TIER_LABELS[tier] ?? tier}
                </Chip>
                <span className="text-default-400 text-sm font-normal">
                  {(grouped[tier] ?? []).length} badge{(grouped[tier] ?? []).length !== 1 ? 's' : ''}
                </span>
              </h2>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {(grouped[tier] ?? []).map((badge) => {
                  const IconComp = CLASS_ICONS[badge.badge_class] ?? Award;
                  const isUpdating = updating === badge.key;
                  return (
                    <Card
                      key={badge.key}
                      shadow="sm"
                      className={`transition-opacity ${!badge.is_enabled ? 'opacity-50' : ''}`}
                    >
                      <CardBody className="gap-3 p-4">
                        <div className="flex items-start justify-between">
                          <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                              <IconComp size={20} />
                            </div>
                            <div className="min-w-0">
                              <p className="font-medium text-foreground truncate">{badge.name}</p>
                              <p className="text-xs text-default-500 line-clamp-2">{badge.description}</p>
                            </div>
                          </div>
                          <Switch
                            size="sm"
                            isSelected={badge.is_enabled}
                            isDisabled={badge.badge_tier === 'core' || isUpdating}
                            onValueChange={(val) => handleToggle(badge, val)}
                            aria-label={`Toggle ${badge.name}`}
                          />
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                          <Chip size="sm" variant="flat" color={RARITY_COLORS[badge.rarity] ?? 'default'}>
                            {badge.rarity}
                          </Chip>
                          {badge.threshold > 0 && (
                            <Chip size="sm" variant="flat" color="default">
                              Threshold: {badge.threshold}
                            </Chip>
                          )}
                          {badge.xp_value > 0 && (
                            <Chip size="sm" variant="flat" color="warning">
                              {badge.xp_value} XP
                            </Chip>
                          )}
                          {badge.has_override && (
                            <Chip size="sm" variant="dot" color="secondary">
                              Customized
                            </Chip>
                          )}
                        </div>
                        {badge.has_override && (
                          <Button
                            size="sm"
                            variant="light"
                            color="danger"
                            startContent={<RotateCcw size={14} />}
                            isLoading={isUpdating}
                            onPress={() => handleReset(badge)}
                            className="self-end"
                          >
                            Reset to Default
                          </Button>
                        )}
                      </CardBody>
                    </Card>
                  );
                })}
              </div>
            </div>
          ))
      )}
    </div>
  );
}

export default BadgeConfiguration;
