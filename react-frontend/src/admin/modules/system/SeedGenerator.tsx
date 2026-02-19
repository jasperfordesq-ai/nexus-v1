// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Seed Generator
 * Generate sample/test data for development and testing environments.
 */

import { useState, useRef } from 'react';
import { Card, CardBody, CardHeader, Button, Checkbox, Input } from '@heroui/react';
import { Database, Play, AlertTriangle } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminTools } from '../../api/adminApi';

const SEED_OPTIONS = [
  { key: 'users', label: 'Users', description: 'Generate sample user accounts', count: 50 },
  { key: 'listings', label: 'Listings', description: 'Generate service offers and requests', count: 100 },
  { key: 'transactions', label: 'Transactions', description: 'Generate time credit transactions', count: 200 },
  { key: 'feed_posts', label: 'Feed Posts', description: 'Generate social feed content', count: 75 },
  { key: 'events', label: 'Events', description: 'Generate community events', count: 20 },
  { key: 'groups', label: 'Groups', description: 'Generate community groups', count: 10 },
  { key: 'messages', label: 'Messages', description: 'Generate private messages', count: 150 },
  { key: 'badges', label: 'Badges', description: 'Award random badges to users', count: 100 },
];

export function SeedGenerator() {
  usePageTitle('Admin - Seed Generator');
  const toast = useToast();
  const [selected, setSelected] = useState<string[]>([]);
  const [running, setRunning] = useState(false);
  // Store mutable count values by key
  const countsRef = useRef<Record<string, number>>(
    Object.fromEntries(SEED_OPTIONS.map(opt => [opt.key, opt.count]))
  );

  const toggleOption = (key: string) => {
    setSelected(prev => prev.includes(key) ? prev.filter(k => k !== key) : [...prev, key]);
  };

  const handleCountChange = (key: string, value: string) => {
    const num = parseInt(value, 10);
    if (!isNaN(num) && num > 0) {
      countsRef.current[key] = num;
    }
  };

  const handleRun = async () => {
    if (selected.length === 0) return;

    setRunning(true);
    try {
      const counts: Record<string, number> = {};
      for (const key of selected) {
        counts[key] = countsRef.current[key] ?? SEED_OPTIONS.find(o => o.key === key)?.count ?? 10;
      }

      await adminTools.runSeedGenerator({ types: selected, counts });
      toast.success(
        'Seed data generated',
        `Generated data for: ${selected.join(', ')}`
      );
    } catch {
      toast.error('Seed generation failed', 'An error occurred while generating test data.');
    } finally {
      setRunning(false);
    }
  };

  return (
    <div>
      <PageHeader title="Seed Generator" description="Generate test data for development environments" />

      <div className="rounded-lg border border-warning-200 bg-warning-50 p-4 mb-4 flex items-start gap-3">
        <AlertTriangle size={20} className="text-warning shrink-0 mt-0.5" />
        <div>
          <p className="font-medium text-warning-700">Development Only</p>
          <p className="text-sm text-warning-600">This tool generates fake data. Only use in development or testing environments. Never run on production.</p>
        </div>
      </div>

      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold flex items-center gap-2">
            <Database size={20} /> Data Types
          </h3>
        </CardHeader>
        <CardBody>
          <div className="space-y-3">
            {SEED_OPTIONS.map(opt => (
              <div key={opt.key} className="flex items-center justify-between rounded-lg border border-default-200 p-3">
                <Checkbox isSelected={selected.includes(opt.key)} onValueChange={() => toggleOption(opt.key)}>
                  <div>
                    <p className="font-medium">{opt.label}</p>
                    <p className="text-xs text-default-400">{opt.description}</p>
                  </div>
                </Checkbox>
                <Input
                  size="sm"
                  type="number"
                  defaultValue={String(opt.count)}
                  className="w-20"
                  variant="bordered"
                  aria-label={`${opt.label} count`}
                  onValueChange={(val) => handleCountChange(opt.key, val)}
                />
              </div>
            ))}
          </div>
          <div className="flex justify-end mt-4">
            <Button
              color="primary"
              startContent={!running ? <Play size={16} /> : undefined}
              onPress={handleRun}
              isLoading={running}
              isDisabled={selected.length === 0}
            >
              Generate {selected.length} Type{selected.length !== 1 ? 's' : ''}
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default SeedGenerator;
