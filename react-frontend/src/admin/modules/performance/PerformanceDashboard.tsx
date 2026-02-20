// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect } from 'react';
import { Card, Button, Tabs, Tab, Chip, Spinner } from '@heroui/react';
import { Activity, Database, Zap, AlertTriangle, Clock, MemoryStick } from 'lucide-react';
import { api } from '@/lib/api';
import { usePageTitle } from '@/hooks/usePageTitle';

interface PerformanceSummary {
  slowest_requests: Array<{
    timestamp: string;
    endpoint: string;
    method: string;
    duration_ms: number;
    query_count: number;
    memory_mb: number;
    warnings?: string[];
  }>;
  slowest_queries: Array<{
    timestamp: string;
    duration_ms: number;
    sql: string;
    caller?: {
      class: string;
      function: string;
      file: string;
      line: number;
    };
  }>;
  memory_spikes: Array<{
    timestamp: string;
    endpoint: string;
    memory_mb: number;
    peak_memory_mb: number;
  }>;
  request_volume: Record<string, number>;
  n_plus_one_warnings: number;
  total_requests: number;
  total_slow_queries: number;
}

export default function PerformanceDashboard() {
  usePageTitle('Performance Monitoring');

  const [summary, setSummary] = useState<PerformanceSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [hours, setHours] = useState(24);
  const [selectedTab, setSelectedTab] = useState('requests');

  useEffect(() => {
    loadSummary();
  }, [hours]);

  const loadSummary = async () => {
    setLoading(true);
    try {
      const response = await api.get<PerformanceSummary>(`/v2/metrics/summary?hours=${hours}`);
      if (response.data) {
        setSummary(response.data);
      }
    } catch (error) {
      console.error('Failed to load performance summary:', error);
    } finally {
      setLoading(false);
    }
  };

  const formatDuration = (ms: number) => {
    if (ms < 1000) return `${Math.round(ms)}ms`;
    return `${(ms / 1000).toFixed(2)}s`;
  };

  const getDurationColor = (ms: number) => {
    if (ms < 100) return 'success';
    if (ms < 500) return 'warning';
    return 'danger';
  };

  const formatTimestamp = (timestamp: string) => {
    return new Date(timestamp).toLocaleString();
  };

  const renderStats = () => {
    if (!summary) return null;

    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-primary/10 rounded-lg">
              <Activity className="w-5 h-5 text-primary" />
            </div>
            <div>
              <div className="text-sm text-default-500">Total Requests</div>
              <div className="text-2xl font-bold">{summary.total_requests}</div>
            </div>
          </div>
        </Card>

        <Card className="p-4">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-warning/10 rounded-lg">
              <Clock className="w-5 h-5 text-warning" />
            </div>
            <div>
              <div className="text-sm text-default-500">Slow Queries</div>
              <div className="text-2xl font-bold">{summary.total_slow_queries}</div>
            </div>
          </div>
        </Card>

        <Card className="p-4">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-danger/10 rounded-lg">
              <AlertTriangle className="w-5 h-5 text-danger" />
            </div>
            <div>
              <div className="text-sm text-default-500">N+1 Warnings</div>
              <div className="text-2xl font-bold">{summary.n_plus_one_warnings}</div>
            </div>
          </div>
        </Card>

        <Card className="p-4">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-secondary/10 rounded-lg">
              <MemoryStick className="w-5 h-5 text-secondary" />
            </div>
            <div>
              <div className="text-sm text-default-500">Memory Spikes</div>
              <div className="text-2xl font-bold">{summary.memory_spikes.length}</div>
            </div>
          </div>
        </Card>
      </div>
    );
  };

  const renderSlowRequests = () => {
    if (!summary || summary.slowest_requests.length === 0) {
      return (
        <div className="text-center py-8 text-default-400">
          No slow requests in the selected time period
        </div>
      );
    }

    return (
      <div className="space-y-3">
        {summary.slowest_requests.map((request, index) => (
          <Card key={index} className="p-4">
            <div className="flex items-start justify-between gap-4">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-2">
                  <Chip size="sm" variant="flat">{request.method}</Chip>
                  <code className="text-sm truncate">{request.endpoint}</code>
                </div>
                <div className="flex items-center gap-4 text-sm text-default-500">
                  <span>{formatTimestamp(request.timestamp)}</span>
                  <span>{request.query_count} queries</span>
                  <span>{request.memory_mb.toFixed(2)} MB</span>
                </div>
                {request.warnings && request.warnings.length > 0 && (
                  <div className="flex gap-2 mt-2">
                    {request.warnings.map((warning, i) => (
                      <Chip key={i} size="sm" color="warning" variant="flat">
                        {warning.replace(/_/g, ' ')}
                      </Chip>
                    ))}
                  </div>
                )}
              </div>
              <Chip
                color={getDurationColor(request.duration_ms)}
                variant="flat"
                size="lg"
              >
                {formatDuration(request.duration_ms)}
              </Chip>
            </div>
          </Card>
        ))}
      </div>
    );
  };

  const renderSlowQueries = () => {
    if (!summary || summary.slowest_queries.length === 0) {
      return (
        <div className="text-center py-8 text-default-400">
          No slow queries in the selected time period
        </div>
      );
    }

    return (
      <div className="space-y-3">
        {summary.slowest_queries.map((query, index) => (
          <Card key={index} className="p-4">
            <div className="flex items-start justify-between gap-4">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-2">
                  <Database className="w-4 h-4 text-primary" />
                  <span className="text-sm text-default-500">{formatTimestamp(query.timestamp)}</span>
                </div>
                <code className="text-sm bg-default-100 dark:bg-default-50 p-2 rounded block overflow-x-auto">
                  {query.sql}
                </code>
                {query.caller && (
                  <div className="mt-2 text-xs text-default-500">
                    Called from: {query.caller.class}::{query.caller.function} ({query.caller.file}:{query.caller.line})
                  </div>
                )}
              </div>
              <Chip
                color={getDurationColor(query.duration_ms)}
                variant="flat"
                size="lg"
              >
                {formatDuration(query.duration_ms)}
              </Chip>
            </div>
          </Card>
        ))}
      </div>
    );
  };

  const renderMemorySpikes = () => {
    if (!summary || summary.memory_spikes.length === 0) {
      return (
        <div className="text-center py-8 text-default-400">
          No memory spikes in the selected time period
        </div>
      );
    }

    return (
      <div className="space-y-3">
        {summary.memory_spikes.map((spike, index) => (
          <Card key={index} className="p-4">
            <div className="flex items-start justify-between gap-4">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-2">
                  <MemoryStick className="w-4 h-4 text-secondary" />
                  <code className="text-sm truncate">{spike.endpoint}</code>
                </div>
                <div className="flex items-center gap-4 text-sm text-default-500">
                  <span>{formatTimestamp(spike.timestamp)}</span>
                  <span>Peak: {spike.peak_memory_mb.toFixed(2)} MB</span>
                </div>
              </div>
              <Chip color="secondary" variant="flat" size="lg">
                {spike.memory_mb.toFixed(2)} MB
              </Chip>
            </div>
          </Card>
        ))}
      </div>
    );
  };

  const renderVolumeChart = () => {
    if (!summary || Object.keys(summary.request_volume).length === 0) {
      return (
        <div className="text-center py-8 text-default-400">
          No volume data available
        </div>
      );
    }

    const hours = Object.keys(summary.request_volume).sort();
    const maxVolume = Math.max(...Object.values(summary.request_volume));

    return (
      <div className="space-y-2">
        {hours.map((hour) => {
          const volume = summary.request_volume[hour];
          const percentage = (volume / maxVolume) * 100;

          return (
            <div key={hour} className="flex items-center gap-4">
              <div className="w-32 text-sm text-default-500">{hour}</div>
              <div className="flex-1 bg-default-100 dark:bg-default-50 rounded-full h-8 overflow-hidden">
                <div
                  className="bg-primary h-full flex items-center justify-end px-3 transition-all"
                  style={{ width: `${percentage}%` }}
                >
                  {percentage > 20 && (
                    <span className="text-sm text-white font-medium">{volume}</span>
                  )}
                </div>
              </div>
              {percentage <= 20 && (
                <div className="w-12 text-sm text-default-500">{volume}</div>
              )}
            </div>
          );
        })}
      </div>
    );
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-96">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold flex items-center gap-2">
            <Zap className="w-7 h-7 text-primary" />
            Performance Monitoring
          </h1>
          <p className="text-default-500 mt-1">
            Track slow queries, requests, and memory usage
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant={hours === 1 ? 'solid' : 'flat'}
            color="primary"
            onPress={() => setHours(1)}
          >
            1 Hour
          </Button>
          <Button
            size="sm"
            variant={hours === 24 ? 'solid' : 'flat'}
            color="primary"
            onPress={() => setHours(24)}
          >
            24 Hours
          </Button>
          <Button
            size="sm"
            variant={hours === 168 ? 'solid' : 'flat'}
            color="primary"
            onPress={() => setHours(168)}
          >
            7 Days
          </Button>
          <Button
            size="sm"
            variant="bordered"
            onPress={loadSummary}
          >
            Refresh
          </Button>
        </div>
      </div>

      {renderStats()}

      <Card className="p-6">
        <Tabs
          selectedKey={selectedTab}
          onSelectionChange={(key) => setSelectedTab(key as string)}
          aria-label="Performance metrics"
        >
          <Tab key="requests" title="Slow Requests">
            <div className="py-4">{renderSlowRequests()}</div>
          </Tab>
          <Tab key="queries" title="Slow Queries">
            <div className="py-4">{renderSlowQueries()}</div>
          </Tab>
          <Tab key="memory" title="Memory Spikes">
            <div className="py-4">{renderMemorySpikes()}</div>
          </Tab>
          <Tab key="volume" title="Request Volume">
            <div className="py-4">{renderVolumeChart()}</div>
          </Tab>
        </Tabs>
      </Card>

      {summary && summary.n_plus_one_warnings > 0 && (
        <Card className="p-4 mt-6 border-l-4 border-warning">
          <div className="flex items-start gap-3">
            <AlertTriangle className="w-5 h-5 text-warning mt-0.5" />
            <div>
              <h3 className="font-semibold text-warning mb-1">N+1 Query Warnings Detected</h3>
              <p className="text-sm text-default-600">
                {summary.n_plus_one_warnings} request(s) executed more than 10 database queries, which may indicate N+1 query problems.
                Check the "Slow Requests" tab for requests with high query counts.
              </p>
            </div>
          </div>
        </Card>
      )}
    </div>
  );
}
