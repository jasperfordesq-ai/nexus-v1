// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api';
import { normalizeGroupApiError, unwrapGroupResponse } from './core';

export interface GroupAnalyticsKpiData {
  total_members: number;
  active_members: number;
  participation_rate: number;
  avg_posts_per_day: number;
}

export interface GroupAnalyticsGrowthPoint {
  date: string;
  total_members: number;
  new_members: number;
}

export interface GroupAnalyticsEngagementPoint {
  date: string;
  posts: number;
  discussions: number;
  active_members: number;
}

export interface GroupAnalyticsContributor {
  user_id: number;
  name: string;
  avatar_url: string | null;
  post_count: number;
}

export interface GroupAnalyticsActivityBreakdown {
  type: string;
  count: number;
}

export interface GroupAnalyticsRetentionCohort {
  month: string;
  joined: number;
  still_active: number;
  retention_pct: number;
}

export interface GroupAnalyticsComparativeStats {
  your_members: number;
  avg_members: number;
  your_activity: number;
  avg_activity: number;
  percentile_rank: number;
}

export interface GroupAnalyticsDashboard {
  kpi: GroupAnalyticsKpiData;
  growth: GroupAnalyticsGrowthPoint[];
  engagement: GroupAnalyticsEngagementPoint[];
  top_contributors: GroupAnalyticsContributor[];
  activity_breakdown: GroupAnalyticsActivityBreakdown[];
  retention: GroupAnalyticsRetentionCohort[];
  comparative: GroupAnalyticsComparativeStats;
}

export type GroupAnalyticsDaysRange = 7 | 30 | 90;
export type GroupAnalyticsExport = 'members' | 'activity';

export interface GetGroupAnalyticsOptions {
  signal?: AbortSignal;
}

type UnknownRecord = Record<string, unknown>;

function isRecord(value: unknown): value is UnknownRecord {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function numberValue(value: unknown): number {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    if (Number.isFinite(parsed)) return parsed;
  }
  return 0;
}

function recordValue(value: unknown): UnknownRecord {
  return isRecord(value) ? value : {};
}

function arrayValue(value: unknown): unknown[] {
  return Array.isArray(value) ? value : [];
}

function normalizeDashboard(payload: unknown): GroupAnalyticsDashboard {
  if (!isRecord(payload)) {
    throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
  }

  const engagement = payload.engagement;
  const activity = payload.activity_breakdown ?? payload.activity;
  const overview = recordValue(payload.overview);
  const explicitKpi = recordValue(payload.kpi);
  const engagementSummary = isRecord(engagement)
    ? recordValue(engagement.summary)
    : {};
  const growth = arrayValue(payload.member_growth ?? payload.growth).map((point) => {
    const row = recordValue(point);
    return {
      date: typeof row.date === 'string' ? row.date : '',
      total_members: numberValue(row.total_members ?? row.members),
      new_members: numberValue(row.new_members),
    };
  });
  const normalizedActivity = Array.isArray(activity)
    ? activity.map((item) => {
        const row = recordValue(item);
        return {
          type: typeof row.type === 'string' ? row.type : '',
          count: numberValue(row.count),
        };
      })
    : Object.entries(recordValue(activity))
        .filter(([type]) => type !== 'total')
        .map(([type, count]) => ({ type, count: numberValue(count) }));
  const retention = arrayValue(payload.retention).map((item) => {
    const row = recordValue(item);
    return {
      month: typeof row.month === 'string' ? row.month : '',
      joined: numberValue(row.joined),
      still_active: numberValue(row.still_active),
      retention_pct: numberValue(row.retention_pct ?? row.retention_rate),
    };
  });
  const comparative = recordValue(payload.comparative);

  return {
    kpi: {
      total_members: numberValue(explicitKpi.total_members ?? overview.total_members),
      active_members: numberValue(explicitKpi.active_members ?? engagementSummary.active_members),
      participation_rate: numberValue(explicitKpi.participation_rate ?? engagementSummary.participation_rate),
      avg_posts_per_day: numberValue(explicitKpi.avg_posts_per_day ?? engagementSummary.avg_posts_per_day),
    },
    growth,
    engagement: (
      isRecord(engagement) && Array.isArray(engagement.timeline)
        ? engagement.timeline
        : engagement ?? []
    ) as GroupAnalyticsEngagementPoint[],
    top_contributors: arrayValue(payload.top_contributors) as GroupAnalyticsContributor[],
    activity_breakdown: normalizedActivity,
    retention,
    comparative: {
      your_members: numberValue(comparative.your_members ?? comparative.group_members),
      avg_members: numberValue(comparative.avg_members),
      your_activity: numberValue(comparative.your_activity),
      avg_activity: numberValue(comparative.avg_activity),
      percentile_rank: numberValue(comparative.percentile_rank ?? comparative.percentile),
    },
  };
}

/** Fetch and normalize the aggregate Groups analytics dashboard. */
export async function getGroupAnalyticsDashboard(
  groupId: number,
  days: GroupAnalyticsDaysRange,
  options: GetGroupAnalyticsOptions = {},
): Promise<GroupAnalyticsDashboard> {
  try {
    const response = await api.get<unknown>(
      `/v2/groups/${groupId}/analytics?days=${days}`,
      { signal: options.signal },
    );
    return normalizeDashboard(unwrapGroupResponse(response));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

/** Download an analytics export through the authenticated, tenant-aware client. */
export async function downloadGroupAnalyticsExport(
  groupId: number,
  type: GroupAnalyticsExport,
): Promise<void> {
  try {
    await api.download(
      `/v2/groups/${groupId}/analytics/export/${type}`,
      { filename: `group-${groupId}-${type}.csv` },
    );
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}
