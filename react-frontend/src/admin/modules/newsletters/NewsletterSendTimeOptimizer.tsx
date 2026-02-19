// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter Send-Time Optimizer
 * Heatmap visualization of optimal send times based on engagement data
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Button, Card, CardBody, CardHeader, Select, SelectItem, Chip,
} from '@heroui/react';
import { Clock, RefreshCw, TrendingUp } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminNewsletters } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { SendTimeData } from '../../api/types';

export function NewsletterSendTimeOptimizer() {
  usePageTitle('Admin - Send-Time Optimizer');
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<SendTimeData | null>(null);
  const [days, setDays] = useState(30);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.getSendTimeData({ days });
      if (res.success && res.data) {
        setData(res.data as SendTimeData);
      }
    } catch {
      setData(null);
    }
    setLoading(false);
  }, [days]);

  useEffect(() => { loadData(); }, [loadData]);

  const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const hours = Array.from({ length: 24 }, (_, i) => i);

  // Build heatmap matrix
  const heatmapMatrix: Record<number, Record<number, number>> = {};
  for (let d = 1; d <= 7; d++) {
    heatmapMatrix[d] = {};
    for (let h = 0; h < 24; h++) {
      heatmapMatrix[d][h] = 0;
    }
  }

  if (data?.heatmap) {
    data.heatmap.forEach(cell => {
      heatmapMatrix[cell.day_of_week] = heatmapMatrix[cell.day_of_week] || {};
      heatmapMatrix[cell.day_of_week][cell.hour] = cell.engagement_score;
    });
  }

  const maxScore = data?.heatmap
    ? Math.max(...data.heatmap.map(c => c.engagement_score), 1)
    : 1;

  const getHeatColor = (score: number) => {
    if (score === 0) return 'bg-default-100 dark:bg-default-50';
    const intensity = Math.min(score / maxScore, 1);
    if (intensity > 0.7) return 'bg-success-500 dark:bg-success-400';
    if (intensity > 0.4) return 'bg-success-300 dark:bg-success-300';
    if (intensity > 0.2) return 'bg-success-200 dark:bg-success-200';
    return 'bg-success-100 dark:bg-success-100';
  };

  const formatHour = (hour: number) => {
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const h = hour % 12 || 12;
    return `${h}${ampm}`;
  };

  return (
    <div>
      <PageHeader
        title="Send-Time Optimizer"
        description="Optimize newsletter send times based on engagement patterns"
        actions={
          <div className="flex gap-2">
            <Select
              label="Time Period"
              selectedKeys={[String(days)]}
              onSelectionChange={(keys) => setDays(Number(Array.from(keys)[0]))}
              className="w-32"
              size="sm"
            >
              <SelectItem key="7">7 days</SelectItem>
              <SelectItem key="30">30 days</SelectItem>
              <SelectItem key="60">60 days</SelectItem>
              <SelectItem key="90">90 days</SelectItem>
            </Select>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
            >
              Refresh
            </Button>
          </div>
        }
      />

      <div className="grid gap-6">
        {/* Recommendations */}
        {data?.recommendations && data.recommendations.length > 0 && (
          <Card>
            <CardHeader className="flex gap-2 items-center">
              <TrendingUp size={20} className="text-success" />
              <span>Top Recommended Send Times</span>
            </CardHeader>
            <CardBody>
              <div className="grid gap-3 md:grid-cols-3">
                {data.recommendations.map((rec, idx) => (
                  <Card key={idx} className="bg-success-50 dark:bg-success-50/10">
                    <CardBody className="gap-2">
                      <div className="flex items-center gap-2">
                        <Chip size="sm" color="success" variant="flat">
                          #{idx + 1}
                        </Chip>
                        <span className="text-sm font-medium">{rec.description}</span>
                      </div>
                      <div className="flex items-baseline gap-2">
                        <span className="text-2xl font-bold text-success">{rec.score}</span>
                        <span className="text-sm text-default-500">engagements</span>
                      </div>
                    </CardBody>
                  </Card>
                ))}
              </div>
            </CardBody>
          </Card>
        )}

        {/* Heatmap */}
        <Card>
          <CardHeader className="flex gap-2 items-center">
            <Clock size={20} />
            <span>Engagement Heatmap</span>
          </CardHeader>
          <CardBody>
            {loading ? (
              <div className="flex items-center justify-center py-12">
                <div className="text-default-400">Loading heatmap...</div>
              </div>
            ) : data && data.heatmap.length > 0 ? (
              <div className="overflow-x-auto">
                <div className="inline-block min-w-full">
                  <div className="grid gap-1" style={{ gridTemplateColumns: 'auto repeat(24, 1fr)' }}>
                    {/* Header row - hours */}
                    <div></div>
                    {hours.map(h => (
                      <div key={h} className="text-center text-xs text-default-500 font-medium py-1">
                        {formatHour(h)}
                      </div>
                    ))}

                    {/* Data rows - days */}
                    {[1, 2, 3, 4, 5, 6, 7].map(day => (
                      <div key={day} className="contents">
                        <div className="flex items-center justify-end pr-2 text-sm font-medium text-default-600">
                          {dayNames[day - 1]}
                        </div>
                        {hours.map(hour => {
                          const score = heatmapMatrix[day]?.[hour] || 0;
                          return (
                            <div
                              key={`${day}-${hour}`}
                              className={`aspect-square rounded ${getHeatColor(score)} cursor-pointer transition-transform hover:scale-110 flex items-center justify-center`}
                              title={`${dayNames[day - 1]} ${formatHour(hour)}: ${score} engagements`}
                            >
                              {score > 0 && (
                                <span className="text-xs font-bold text-white drop-shadow">
                                  {score}
                                </span>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    ))}
                  </div>

                  {/* Legend */}
                  <div className="flex items-center gap-2 mt-6 justify-center">
                    <span className="text-sm text-default-500">Low</span>
                    <div className="flex gap-1">
                      <div className="w-4 h-4 rounded bg-default-100 dark:bg-default-50"></div>
                      <div className="w-4 h-4 rounded bg-success-100"></div>
                      <div className="w-4 h-4 rounded bg-success-200"></div>
                      <div className="w-4 h-4 rounded bg-success-300"></div>
                      <div className="w-4 h-4 rounded bg-success-500"></div>
                    </div>
                    <span className="text-sm text-default-500">High</span>
                  </div>
                </div>
              </div>
            ) : (
              <div className="flex flex-col items-center gap-2 py-12 text-default-400">
                <Clock size={40} />
                <p>{data?.insights || 'Not enough engagement data yet.'}</p>
                <p className="text-xs">Send a few newsletters to see engagement patterns.</p>
              </div>
            )}
          </CardBody>
        </Card>

        {/* Insights */}
        {data?.insights && (
          <Card className="bg-primary-50 dark:bg-primary-50/10">
            <CardBody className="flex-row items-center gap-3">
              <TrendingUp size={20} className="text-primary" />
              <p className="text-sm text-primary-700 dark:text-primary-300">
                {data.insights}
              </p>
            </CardBody>
          </Card>
        )}
      </div>
    </div>
  );
}

export default NewsletterSendTimeOptimizer;
