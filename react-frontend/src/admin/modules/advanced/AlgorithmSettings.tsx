// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Algorithm Settings
 * Configure matching, ranking, and recommendation algorithm parameters.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Slider, Button, Spinner } from '@heroui/react';
import { Settings, Save } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminSettings } from '../../api/adminApi';

export function AlgorithmSettings() {
  usePageTitle('Admin - Algorithm Settings');
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState<Record<string, unknown>>({
    category_match_weight: 60,
    skill_match_weight: 50,
    proximity_weight: 40,
    freshness_weight: 30,
    quality_score_weight: 45,
    activity_bonus: 35,
  });

  useEffect(() => {
    adminSettings.getFeedAlgorithm()
      .then(res => {
        if (res.data) {
          setFormData(prev => ({ ...prev, ...res.data }));
        }
      })
      .catch(() => toast.error('Failed to load algorithm settings'))
      .finally(() => setLoading(false));
  }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      const res = await adminSettings.updateFeedAlgorithm(formData);

      if (res.success) {
        toast.success('Algorithm settings saved successfully');
      } else {
        const error = (res as { error?: string }).error || 'Save failed';
        toast.error(error);
      }
    } catch (err) {
      toast.error('Failed to save algorithm settings');
      console.error('Algorithm settings save error:', err);
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
      <PageHeader title="Algorithm Settings" description="Configure matching, ranking, and recommendation algorithms" />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Settings size={20} /> Matching Algorithm</h3></CardHeader>
          <CardBody className="gap-6">
            <div>
              <p className="text-sm font-medium mb-2">Category Match Weight</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.category_match_weight)} onChange={(v) => updateField('category_match_weight', v)} step={5} label="Weight for category similarity" className="max-w-md" />
            </div>
            <div>
              <p className="text-sm font-medium mb-2">Skill Match Weight</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.skill_match_weight)} onChange={(v) => updateField('skill_match_weight', v)} step={5} label="Weight for skill overlap" className="max-w-md" />
            </div>
            <div>
              <p className="text-sm font-medium mb-2">Proximity Weight</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.proximity_weight)} onChange={(v) => updateField('proximity_weight', v)} step={5} label="Weight for geographic proximity" className="max-w-md" />
            </div>
            <div>
              <p className="text-sm font-medium mb-2">Freshness Weight</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.freshness_weight)} onChange={(v) => updateField('freshness_weight', v)} step={5} label="Boost newer listings" className="max-w-md" />
            </div>
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Listing Ranking</h3></CardHeader>
          <CardBody className="gap-6">
            <div>
              <p className="text-sm font-medium mb-2">Quality Score Weight</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.quality_score_weight)} onChange={(v) => updateField('quality_score_weight', v)} step={5} label="Weight for listing completeness and reviews" className="max-w-md" />
            </div>
            <div>
              <p className="text-sm font-medium mb-2">Activity Bonus</p>
              <Slider minValue={0} maxValue={100} value={Number(formData.activity_bonus)} onChange={(v) => updateField('activity_bonus', v)} step={5} label="Boost listings from active users" className="max-w-md" />
            </div>
          </CardBody>
        </Card>

        <div className="flex justify-end">
          <Button color="primary" startContent={<Save size={16} />} onPress={handleSave} isLoading={saving}>Save Settings</Button>
        </div>
      </div>
    </div>
  );
}

export default AlgorithmSettings;
