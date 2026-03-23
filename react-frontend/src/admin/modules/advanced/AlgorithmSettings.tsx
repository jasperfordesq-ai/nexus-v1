// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Algorithm Settings
 * Configure EdgeRank (feed), MatchRank (listings), CommunityRank (members),
 * and SmartMatch parameters. Shows algorithm health status.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card, CardBody, CardHeader,
  Slider, Button, Spinner, Switch, Chip, Divider,
} from '@heroui/react';
import {
  Settings, Save, Activity, Database, Cpu,
  CheckCircle, XCircle, RefreshCw, Search,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';

import { useTranslation } from 'react-i18next';
// ─── Types ────────────────────────────────────────────────────────────────────

interface AlgorithmWeights {
  [key: string]: number;
}

interface AlgorithmArea {
  area: string;
  label: string;
  description: string;
  enabled: boolean;
  weights: AlgorithmWeights;
  params: SliderParam[];
}

interface SliderParam {
  key: string;
  label: string;
  description: string;
  min: number;
  max: number;
  step: number;
}

interface HealthStatus {
  fulltext: {
    listings: boolean;
    users: boolean;
    feed_activity: boolean;
  };
  collaborative_filtering: {
    listing_interactions: number;
    member_interactions: number;
  };
  embeddings: {
    listing_count: number;
    user_count: number;
    total: number;
  };
  search?: {
    meilisearch_available: boolean;
    listing_index_count?: number;
  };
}

// ─── Algorithm Parameter Definitions ─────────────────────────────────────────

const ALGORITHM_AREAS: Omit<AlgorithmArea, 'enabled' | 'weights'>[] = [
  {
    area: 'feed',
    label: 'EdgeRank — Feed',
    description: 'Controls how feed_activity posts are ranked using affinity, content type, time decay, and engagement.',
    params: [
      { key: 'affinity_weight',      label: 'Affinity Weight',       description: 'Boost posts from people you\'re connected to',   min: 0, max: 1, step: 0.05 },
      { key: 'content_type_weight',  label: 'Content Type Weight',   description: 'Boost events & challenges over plain posts',      min: 0, max: 1, step: 0.05 },
      { key: 'time_decay_weight',    label: 'Time Decay Weight',     description: 'How quickly older posts lose ranking score',      min: 0, max: 1, step: 0.05 },
      { key: 'engagement_weight',    label: 'Engagement Weight',     description: 'Boost posts with more likes and comments',        min: 0, max: 1, step: 0.05 },
      { key: 'freshness_minimum',    label: 'Freshness Floor',       description: 'Minimum score for very recent posts (0–1)',       min: 0, max: 0.5, step: 0.05 },
      { key: 'half_life_hours',      label: 'Half-Life (hours)',     description: 'Hours until a post\'s freshness score halves',   min: 1, max: 168, step: 1 },
    ],
  },
  {
    area: 'listings',
    label: 'MatchRank — Listings',
    description: 'Controls how listings are ranked using skill match, location, quality, and collaborative filtering.',
    params: [
      { key: 'skill_match_weight',   label: 'Skill Match Weight',    description: 'Weight for overlapping skills between viewer and listing',   min: 0, max: 1, step: 0.05 },
      { key: 'location_weight',      label: 'Location Weight',       description: 'Proximity boost for listings near the viewer',               min: 0, max: 1, step: 0.05 },
      { key: 'quality_weight',       label: 'Quality Weight',        description: 'Boost well-described, reviewed listings (Bayesian avg)',     min: 0, max: 1, step: 0.05 },
      { key: 'freshness_weight',     label: 'Freshness Weight',      description: 'Boost recently updated listings',                            min: 0, max: 1, step: 0.05 },
      { key: 'engagement_weight',    label: 'Engagement Weight',     description: 'Boost listings with more saves and contacts',                min: 0, max: 1, step: 0.05 },
      { key: 'reputation_weight',    label: 'Reputation Weight',     description: 'Boost listings from highly-rated members',                   min: 0, max: 1, step: 0.05 },
    ],
  },
  {
    area: 'members',
    label: 'CommunityRank — Members',
    description: 'Controls how members are ranked using reputation (Wilson Score), contribution, and activity.',
    params: [
      { key: 'reputation_weight',    label: 'Reputation Weight',     description: 'Wilson Score — lower-bound of positive exchange ratio',     min: 0, max: 1, step: 0.05 },
      { key: 'contribution_weight',  label: 'Contribution Weight',   description: 'Hours contributed, listings posted, reviews given',         min: 0, max: 1, step: 0.05 },
      { key: 'activity_weight',      label: 'Activity Weight',       description: 'Recent logins, feed posts, and group activity',             min: 0, max: 1, step: 0.05 },
      { key: 'proximity_weight',     label: 'Proximity Weight',      description: 'Boost members near the viewer\'s location',                 min: 0, max: 1, step: 0.05 },
      { key: 'skill_match_weight',   label: 'Skill Match Weight',    description: 'Weight for skill overlap between viewer and member',        min: 0, max: 1, step: 0.05 },
    ],
  },
  {
    area: 'matching',
    label: 'SmartMatch — Recommendations',
    description: 'Controls SmartMatchingEngine weights for user-to-listing and user-to-user recommendations.',
    params: [
      { key: 'skill_weight',         label: 'Skill Weight',          description: 'Overlap between user skills and listing/user skills',       min: 0, max: 1, step: 0.05 },
      { key: 'location_weight',      label: 'Location Weight',       description: 'Geographic proximity component',                            min: 0, max: 1, step: 0.05 },
      { key: 'rating_weight',        label: 'Rating Weight',         description: 'Member rating / listing quality component',                 min: 0, max: 1, step: 0.05 },
      { key: 'availability_weight',  label: 'Availability Weight',   description: 'Boost members who set availability hours',                  min: 0, max: 1, step: 0.05 },
    ],
  },
];

// ─── Component ────────────────────────────────────────────────────────────────

export function AlgorithmSettings() {
  const { t } = useTranslation('admin');
  usePageTitle(t('advanced.page_title'));
  const toast = useToast();

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState<string | null>(null);
  const [healthLoading, setHealthLoading] = useState(false);

  const [areas, setAreas] = useState<AlgorithmArea[]>([]);
  const [health, setHealth] = useState<HealthStatus | null>(null);

  const loadConfig = useCallback(async () => {
    try {
      const res = await adminSettings.getAlgorithmConfig();
      const data = (res.data ?? {}) as Record<string, unknown>;

      setAreas(ALGORITHM_AREAS.map(def => {
        const areaData = (data[def.area] ?? {}) as Record<string, unknown>;
        const weights: AlgorithmWeights = {};
        def.params.forEach(p => {
          weights[p.key] = typeof areaData[p.key] === 'number'
            ? (areaData[p.key] as number)
            : getDefaultWeight(def.area, p.key);
        });
        return {
          ...def,
          enabled: areaData.enabled !== false,
          weights,
        };
      }));
    } catch {
      toast.error(t('advanced.failed_to_load_algorithm_settings'));
      // Initialise with defaults
      setAreas(ALGORITHM_AREAS.map(def => ({
        ...def,
        enabled: true,
        weights: Object.fromEntries(def.params.map(p => [p.key, getDefaultWeight(def.area, p.key)])),
      })));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  const loadHealth = useCallback(async () => {
    setHealthLoading(true);
    try {
      const res = await adminSettings.getAlgorithmHealth();
      const raw = (res.data ?? null) as Record<string, unknown> | null;
      if (!raw) { setHealth(null); return; }

      // Normalise backend keys to match frontend HealthStatus shape
      const ft = (raw.fulltext ?? raw.fulltext_indexes ?? {}) as HealthStatus['fulltext'];
      const cf = (raw.collaborative_filtering ?? raw.collaborative_filter ?? {}) as Record<string, number>;
      const emb = (raw.embeddings ?? {}) as Record<string, number>;

      setHealth({
        fulltext: {
          listings: ft?.listings ?? false,
          users: ft?.users ?? false,
          feed_activity: ft?.feed_activity ?? false,
        },
        collaborative_filtering: {
          listing_interactions: cf?.listing_interactions ?? 0,
          member_interactions: cf?.member_interactions ?? cf?.member_pairs ?? 0,
        },
        embeddings: {
          listing_count: emb?.listing_count ?? 0,
          user_count: emb?.user_count ?? 0,
          total: emb?.total ?? ((emb?.listing_count ?? 0) + (emb?.user_count ?? 0)),
        },
        search: raw.search as HealthStatus['search'],
      });
    } catch {
      // Health is optional — don't toast
    } finally {
      setHealthLoading(false);
    }
  }, []);

  useEffect(() => {
    loadConfig();
    loadHealth();
  }, [loadConfig, loadHealth]);

  const updateWeight = (area: string, key: string, value: number) => {
    setAreas(prev => prev.map(a =>
      a.area === area
        ? { ...a, weights: { ...a.weights, [key]: value } }
        : a
    ));
  };

  const toggleEnabled = (area: string, enabled: boolean) => {
    setAreas(prev => prev.map(a => a.area === area ? { ...a, enabled } : a));
  };

  const handleSave = async (areaData: AlgorithmArea) => {
    setSaving(areaData.area);
    try {
      const payload = { enabled: areaData.enabled, ...areaData.weights };
      const res = await adminSettings.updateAlgorithmConfig(areaData.area, payload);
      if ((res as { success?: boolean }).success) {
        toast.success(`${areaData.label} settings saved`);
      } else {
        toast.error(t('advanced.save_failed'));
      }
    } catch {
      toast.error(`Failed to save ${areaData.label}`);
    } finally {
      setSaving(null);
    }
  };

  if (loading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('advanced.algorithm_settings_title')}
        description={t('advanced.algorithm_settings_desc')}
      />

      <div className="space-y-6">

        {/* ── Algorithm Cards ── */}
        {areas.map(areaData => (
          <Card key={areaData.area} shadow="sm">
            <CardHeader className="flex items-start justify-between gap-4">
              <div className="flex items-center gap-3">
                <Cpu size={20} className="text-primary shrink-0" />
                <div>
                  <h3 className="text-base font-semibold">{areaData.label}</h3>
                  <p className="text-sm text-foreground-500">{areaData.description}</p>
                </div>
              </div>
              <Switch
                isSelected={areaData.enabled}
                onValueChange={v => toggleEnabled(areaData.area, v)}
                size="sm"
                aria-label={`Enable ${areaData.label}`}
              >
                {areaData.enabled ? 'Enabled' : 'Disabled'}
              </Switch>
            </CardHeader>

            {areaData.enabled && (
              <CardBody className="gap-5">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                  {areaData.params.map(param => (
                    <div key={param.key}>
                      <p className="text-sm font-medium mb-1">{param.label}</p>
                      <p className="text-xs text-foreground-500 mb-2">{param.description}</p>
                      <Slider
                        minValue={param.min}
                        maxValue={param.max}
                        step={param.step}
                        value={areaData.weights[param.key] ?? param.min}
                        onChange={v => updateWeight(areaData.area, param.key, v as number)}
                        className="max-w-sm"
                        aria-label={param.label}
                        showTooltip
                      />
                      <span className="text-xs text-foreground-500 mt-1 block">
                        {(areaData.weights[param.key] ?? param.min).toFixed(param.step < 1 ? 2 : 0)}
                      </span>
                    </div>
                  ))}
                </div>

                <Divider />

                <div className="flex justify-end">
                  <Button
                    color="primary"
                    size="sm"
                    startContent={<Save size={14} />}
                    onPress={() => handleSave(areaData)}
                    isLoading={saving === areaData.area}
                  >
                    Save {areaData.label}
                  </Button>
                </div>
              </CardBody>
            )}

            {!areaData.enabled && (
              <CardBody>
                <p className="text-sm text-foreground-400 italic">
                  Algorithm disabled — default SQL ordering is used instead.
                </p>
                <div className="flex justify-end mt-3">
                  <Button
                    color="primary"
                    size="sm"
                    startContent={<Save size={14} />}
                    onPress={() => handleSave(areaData)}
                    isLoading={saving === areaData.area}
                  >
                    Save {areaData.label}
                  </Button>
                </div>
              </CardBody>
            )}
          </Card>
        ))}

        {/* ── Health Dashboard ── */}
        <Card shadow="sm">
          <CardHeader className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <Activity size={20} className="text-primary" />
              <div>
                <h3 className="text-base font-semibold">Algorithm Health</h3>
                <p className="text-sm text-foreground-500">
                  Infrastructure status — FULLTEXT indexes, training data, embeddings
                </p>
              </div>
            </div>
            <Button
              size="sm"
              variant="flat"
              startContent={<RefreshCw size={14} className={healthLoading ? 'animate-spin' : ''} />}
              onPress={loadHealth}
              isDisabled={healthLoading}
            >
              Refresh
            </Button>
          </CardHeader>

          <CardBody className="gap-4">
            {healthLoading && !health && (
              <div className="flex justify-center py-4"><Spinner size="sm" /></div>
            )}

            {health && (
              <>
                {/* FULLTEXT */}
                <div>
                  <p className="text-sm font-semibold mb-2 flex items-center gap-2">
                    <Database size={14} /> MySQL FULLTEXT Indexes
                  </p>
                  <div className="flex flex-wrap gap-2">
                    {([
                      ['listings', health.fulltext.listings],
                      ['users',    health.fulltext.users],
                      ['feed_activity', health.fulltext.feed_activity],
                    ] as [string, boolean][]).map(([table, ok]) => (
                      <Chip
                        key={table}
                        size="sm"
                        color={ok ? 'success' : 'danger'}
                        variant="flat"
                        startContent={ok ? <CheckCircle size={12} /> : <XCircle size={12} />}
                      >
                        {table}
                      </Chip>
                    ))}
                  </div>
                  {(!health.fulltext.listings || !health.fulltext.users || !health.fulltext.feed_activity) && (
                    <p className="text-xs text-warning mt-2">
                      Run <code className="bg-default-100 px-1 rounded">php scripts/safe_migrate.php</code> to create missing indexes.
                    </p>
                  )}
                </div>

                <Divider />

                {/* Collaborative Filtering */}
                <div>
                  <p className="text-sm font-semibold mb-2 flex items-center gap-2">
                    <Settings size={14} /> Collaborative Filtering Training Data
                  </p>
                  <div className="flex flex-wrap gap-3 text-sm">
                    <span>
                      <span className="font-medium">{health.collaborative_filtering.listing_interactions.toLocaleString()}</span>
                      <span className="text-foreground-500 ml-1">listing saves</span>
                    </span>
                    <span>
                      <span className="font-medium">{health.collaborative_filtering.member_interactions.toLocaleString()}</span>
                      <span className="text-foreground-500 ml-1">member transactions</span>
                    </span>
                  </div>
                  {health.collaborative_filtering.listing_interactions < 10 && (
                    <p className="text-xs text-foreground-400 mt-1">
                      CF needs ≥10 interactions to produce meaningful recommendations.
                    </p>
                  )}
                </div>

                <Divider />

                {/* Embeddings */}
                <div>
                  <p className="text-sm font-semibold mb-2 flex items-center gap-2">
                    <Cpu size={14} /> Semantic Embeddings (OpenAI)
                  </p>
                  <div className="flex flex-wrap gap-3 text-sm">
                    <span>
                      <span className="font-medium">{health.embeddings.listing_count.toLocaleString()}</span>
                      <span className="text-foreground-500 ml-1">listings</span>
                    </span>
                    <span>
                      <span className="font-medium">{health.embeddings.user_count.toLocaleString()}</span>
                      <span className="text-foreground-500 ml-1">users</span>
                    </span>
                    <Chip
                      size="sm"
                      color={health.embeddings.total > 0 ? 'success' : 'default'}
                      variant="flat"
                    >
                      {health.embeddings.total} total
                    </Chip>
                  </div>
                  {health.embeddings.total === 0 && (
                    <p className="text-xs text-foreground-400 mt-2">
                      Run <code className="bg-default-100 px-1 rounded">php scripts/backfill_embeddings.php --tenant=&lt;id&gt;</code> to generate embeddings.
                    </p>
                  )}
                </div>

                {health.search && (
                  <>
                    <Divider />
                    <div>
                      <p className="text-sm font-semibold mb-2 flex items-center gap-2">
                        <Search size={14} /> Search Engine
                      </p>
                      <div className="flex flex-wrap gap-2">
                        <Chip
                          size="sm"
                          color={health.search.meilisearch_available ? 'success' : 'warning'}
                          variant="flat"
                          startContent={health.search.meilisearch_available ? <CheckCircle size={12} /> : <XCircle size={12} />}
                        >
                          Meilisearch {health.search.meilisearch_available ? 'online' : 'offline (using FULLTEXT fallback)'}
                        </Chip>
                      </div>
                      {!health.search.meilisearch_available && (
                        <p className="text-xs text-foreground-400 mt-2">
                          Run <code className="bg-default-100 px-1 rounded">php scripts/sync_search_index.php --all-tenants</code> after Meilisearch starts.
                        </p>
                      )}
                    </div>
                  </>
                )}
              </>
            )}

            {!health && !healthLoading && (
              <p className="text-sm text-foreground-400">Health data unavailable — check API connectivity.</p>
            )}
          </CardBody>
        </Card>

      </div>
    </div>
  );
}

// ─── Defaults ─────────────────────────────────────────────────────────────────

function getDefaultWeight(area: string, key: string): number {
  const defaults: Record<string, Record<string, number>> = {
    feed: {
      affinity_weight: 0.3,
      content_type_weight: 0.25,
      time_decay_weight: 0.25,
      engagement_weight: 0.2,
      freshness_minimum: 0.1,
      half_life_hours: 24,
    },
    listings: {
      skill_match_weight: 0.3,
      location_weight: 0.2,
      quality_weight: 0.2,
      freshness_weight: 0.15,
      engagement_weight: 0.1,
      reputation_weight: 0.05,
    },
    members: {
      reputation_weight: 0.35,
      contribution_weight: 0.25,
      activity_weight: 0.2,
      proximity_weight: 0.1,
      skill_match_weight: 0.1,
    },
    matching: {
      skill_weight: 0.4,
      location_weight: 0.25,
      rating_weight: 0.2,
      availability_weight: 0.15,
    },
  };
  return defaults[area]?.[key] ?? 0.2;
}

export default AlgorithmSettings;
