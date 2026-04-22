// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Feed Algorithm
 * Configure the social feed ranking algorithm parameters.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Slider, Switch, Button, Spinner } from '@heroui/react';
import { Rss, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';

export function FeedAlgorithm() {
  usePageTitle("Advanced");
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState<Record<string, unknown>>({
    recency_weight: 70,
    engagement_weight: 50,
    connection_weight: 40,
    diversity_factor: 30,
    chronological_mode: false,
    include_polls: true,
    include_events: true,
  });

  useEffect(() => {
    adminSettings.getFeedAlgorithm()
      .then(res => {
        if (res.data) {
          setFormData(prev => ({ ...prev, ...res.data }));
        }
      })
      .catch(() => toast.error("Failed to load feed algorithm settings"))
      .finally(() => setLoading(false));
  }, [toast, t])

  const handleSave = async () => {
    // Validate numeric weights before sending to API
    const weightFields = ['recency_weight', 'engagement_weight', 'connection_weight', 'diversity_factor'] as const;
    for (const field of weightFields) {
      const val = Number(formData[field]);
      if (!Number.isFinite(val) || val < 0 || val > 100) {
        toast.error(`Invalid Weight`);
        return;
      }
    }

    setSaving(true);
    try {
      const res = await adminSettings.updateFeedAlgorithm(formData);

      if (res.success) {
        toast.success("Feed algorithm settings saved successfully");
      } else {
        const error = (res as { error?: string }).error || "Save failed";
        toast.error(error);
      }
    } catch (err) {
      toast.error("Failed to save feed algorithm settings");
    } finally {
      setSaving(false);
    }
  };

  const updateField = (key: string, value: unknown) => {
    setFormData(prev => ({ ...prev, [key]: value }));
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
      <PageHeader title={"Feed Algorithm"} description={"Configure how posts are ranked and surfaced in the community feed"} />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Rss size={20} /> {"Feed Ranking Weights"}</h3></CardHeader>
          <CardBody className="gap-6">
            <div>
              <p className="text-sm font-medium mb-2">{"Recency Weight"}</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.recency_weight)} onChange={(v) => updateField('recency_weight', v)} step={5} label={"How much to prioritise newer content"} className="max-w-md" />
            </div>
            <div>
              <p className="text-sm font-medium mb-2">{"Engagement Weight"}</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.engagement_weight)} onChange={(v) => updateField('engagement_weight', v)} step={5} label={"How much to prioritise liked/commented content"} className="max-w-md" />
            </div>
            <div>
              <p className="text-sm font-medium mb-2">{"Connection Weight"}</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.connection_weight)} onChange={(v) => updateField('connection_weight', v)} step={5} label={"Boost Content from Connected Users"} className="max-w-md" />
            </div>
            <div>
              <p className="text-sm font-medium mb-2">{"Diversity Factor"}</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.diversity_factor)} onChange={(v) => updateField('diversity_factor', v)} step={5} label={"Vary Content Types in the Feed"} className="max-w-md" />
            </div>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">{"Feed Options"}</h3></CardHeader>
          <CardBody className="space-y-3">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{"Chronological Mode"}</p>
                <p className="text-sm text-default-500">{"Show feed items in strict chronological order instead of ranked order"}</p>
              </div>
              <Switch isSelected={!!formData.chronological_mode} onValueChange={(v) => updateField('chronological_mode', v)} aria-label={"Chronological Mode"} />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{"Include Polls"}</p>
                <p className="text-sm text-default-500">{"Include polls in the feed ranking algorithm"}</p>
              </div>
              <Switch isSelected={!!formData.include_polls} onValueChange={(v) => updateField('include_polls', v)} aria-label={"Include Polls"} />
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">{"Include Events"}</p>
                <p className="text-sm text-default-500">{"Include event posts in the feed ranking algorithm"}</p>
              </div>
              <Switch isSelected={!!formData.include_events} onValueChange={(v) => updateField('include_events', v)} aria-label={"Include Events"} />
            </div>
          </CardBody>
        </Card>

        <div className="flex justify-end">
          <Button color="primary" startContent={<Save size={16} />} onPress={handleSave} isLoading={saving} isDisabled={saving}>{"Save Algorithm"}</Button>
        </div>
      </div>
    </div>
  );
}

export default FeedAlgorithm;
