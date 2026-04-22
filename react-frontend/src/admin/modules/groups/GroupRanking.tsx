// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { Card, Table, TableHeader, TableColumn, TableBody, TableRow, TableCell, Button, Switch } from '@heroui/react';
import { RefreshCw, Star } from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { adminGroups } from '@/admin/api/adminApi';
import type { FeaturedGroup } from '@/admin/api/types';

export default function GroupRanking() {
  usePageTitle("Groups");
  const { success, error } = useToast();
  const [groups, setGroups] = useState<FeaturedGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [updating, setUpdating] = useState(false);

  const loadGroups = useCallback(async () => {
    try {
      setLoading(true);
      const response = await adminGroups.getFeaturedGroups();
      setGroups((response.data as FeaturedGroup[]) || []);
    } catch {
      error("Failed to load featured groups");
    } finally {
      setLoading(false);
    }
  }, [error, t])

  useEffect(() => {
    loadGroups();
  }, [loadGroups]);

  const handleUpdateRankings = async () => {
    try {
      setUpdating(true);
      await adminGroups.updateFeaturedGroups();
      success("Rankings Updated");
      loadGroups();
    } catch {
      error("Failed to update rankings");
    } finally {
      setUpdating(false);
    }
  };

  const handleToggleFeatured = async (groupId: number) => {
    try {
      await adminGroups.toggleFeatured(groupId);
      success("Featured status updated");
      loadGroups();
    } catch {
      error("Failed to update featured status");
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">{"Group Ranking"}</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {"View and manage the automatic ranking of groups by activity score"}
          </p>
        </div>
        <Button
          color="primary"
          startContent={<RefreshCw className="w-4 h-4" />}
          onPress={handleUpdateRankings}
          isLoading={updating}
        >
          {"Auto Update Rankings"}
        </Button>
      </div>

      <Card className="p-4">
        <Table aria-label={"Featured Groups Table"}>
          <TableHeader>
            <TableColumn>{"Group"}</TableColumn>
            <TableColumn>{"Members"}</TableColumn>
            <TableColumn>{"Engagement"}</TableColumn>
            <TableColumn>{"Geo Diversity"}</TableColumn>
            <TableColumn>{"Total Score"}</TableColumn>
            <TableColumn>{"Featured"}</TableColumn>
          </TableHeader>
          <TableBody emptyContent={loading ? "Loading groups..." : "No groups found"}>
            {groups.map((group) => (
              <TableRow key={group.group_id}>
                <TableCell>
                  <div className="flex items-center gap-2">
                    {group.is_featured && <Star className="w-4 h-4 text-warning fill-warning" />}
                    <span className="font-medium">{group.name}</span>
                  </div>
                </TableCell>
                <TableCell>{group.member_count ?? 0}</TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <div className="w-16 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                      <div
                        className="h-full bg-primary"
                        style={{ width: `${Math.min(100, Number(group.engagement_score ?? 0))}%` }}
                      ></div>
                    </div>
                    <span className="text-sm">{Number(group.engagement_score ?? 0).toFixed(0)}</span>
                  </div>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <div className="w-16 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                      <div
                        className="h-full bg-success"
                        style={{ width: `${Math.min(100, Number(group.geographic_diversity ?? 0))}%` }}
                      ></div>
                    </div>
                    <span className="text-sm">{Number(group.geographic_diversity ?? 0).toFixed(0)}</span>
                  </div>
                </TableCell>
                <TableCell>
                  <span className="font-bold text-primary">{Number(group.ranking_score ?? 0).toFixed(0)}</span>
                </TableCell>
                <TableCell>
                  <Switch
                    isSelected={!!group.is_featured}
                    onValueChange={() => handleToggleFeatured(group.group_id)}
                  />
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </Card>

      <Card className="p-6">
        <h2 className="text-lg font-semibold mb-3">{"Ranking Algorithm"}</h2>
        <div className="space-y-2 text-sm text-gray-600 dark:text-gray-400">
          <p>
            <strong>{"Score Formula"}</strong>
            {"Total Score = Engagement + Geographic Diversity + Activity"}
          </p>
          <p><strong>{"Engagement Score"}</strong> {"Score based on member activity, posts, and participation in the group"}</p>
          <p><strong>{"Geographic Diversity"}</strong> {"Score based on the geographic spread of group members"}</p>
          <p className="text-xs mt-4">
            {"Rankings are automatically updated based on group activity, member count, and engagement score"}
          </p>
        </div>
      </Card>
    </div>
  );
}
