// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { Card, Table, TableHeader, TableColumn, TableBody, TableRow, TableCell, Chip } from '@heroui/react';
import { TrendingUp, Users, Target } from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { adminGroups } from '@/admin/api/adminApi';
import type { GroupRecommendation } from '@/admin/api/types';

import { useTranslation } from 'react-i18next';
export default function GroupRecommendations() {
  const { t } = useTranslation('admin');
  usePageTitle(t('groups.page_title'));
  const { error } = useToast();
  const [recommendations, setRecommendations] = useState<GroupRecommendation[]>([]);
  const [stats, setStats] = useState({ total: 0, avg_score: 0, join_rate: 0 });
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    try {
      setLoading(true);
      const response = await adminGroups.getRecommendationData({ limit: 50 });
      const data = response.data as { recommendations: GroupRecommendation[]; stats: { total: number; avg_score: number; join_rate: number } };
      setRecommendations(data?.recommendations || []);
      setStats(data?.stats || { total: 0, avg_score: 0, join_rate: 0 });
    } catch {
      error(t('groups.failed_to_load_recommendations'));
    } finally {
      setLoading(false);
    }
  }, [error, t])

  useEffect(() => {
    loadData();
  }, [loadData]);

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold">{t('groups.group_recommendations_title')}</h1>
        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
          {t('groups.group_recommendations_desc')}
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card className="p-6">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center">
              <TrendingUp className="w-6 h-6 text-primary" />
            </div>
            <div>
              <div className="text-sm text-gray-500">{t('groups.total_recommendations')}</div>
              <div className="text-2xl font-bold mt-1">{stats.total}</div>
            </div>
          </div>
        </Card>

        <Card className="p-6">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded-lg bg-success/10 flex items-center justify-center">
              <Target className="w-6 h-6 text-success" />
            </div>
            <div>
              <div className="text-sm text-gray-500">{t('groups.avg_match_score')}</div>
              <div className="text-2xl font-bold mt-1">{stats.avg_score.toFixed(2)}</div>
            </div>
          </div>
        </Card>

        <Card className="p-6">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded-lg bg-warning/10 flex items-center justify-center">
              <Users className="w-6 h-6 text-warning" />
            </div>
            <div>
              <div className="text-sm text-gray-500">{t('groups.join_rate')}</div>
              <div className="text-2xl font-bold mt-1">{stats.join_rate}%</div>
            </div>
          </div>
        </Card>
      </div>

      <Card className="p-4">
        <h2 className="text-lg font-semibold mb-4">{t('groups.recent_recommendations')}</h2>
        <Table aria-label={t('groups.label_recommendations_table')}>
          <TableHeader>
            <TableColumn>{t('groups.col_user')}</TableColumn>
            <TableColumn>{t('groups.col_group')}</TableColumn>
            <TableColumn>{t('groups.col_score')}</TableColumn>
            <TableColumn>{t('groups.col_status')}</TableColumn>
            <TableColumn>{t('groups.col_date')}</TableColumn>
          </TableHeader>
          <TableBody
            emptyContent={loading ? t('groups.loading') : t('groups.no_recommendations_found')}
            items={recommendations}
          >
            {(rec) => (
              <TableRow key={`${rec.user_id}-${rec.group_id}`}>
                <TableCell>{rec.user_name}</TableCell>
                <TableCell>{rec.group_name}</TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <div className="w-24 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                      <div
                        className="h-full bg-primary"
                        style={{ width: `${rec.score * 100}%` }}
                      ></div>
                    </div>
                    <span className="text-sm">{(rec.score * 100).toFixed(0)}%</span>
                  </div>
                </TableCell>
                <TableCell>
                  <Chip size="sm" color={rec.joined ? 'success' : 'default'}>
                    {rec.joined ? t('groups.joined') : t('groups.pending')}
                  </Chip>
                </TableCell>
                <TableCell>{new Date(rec.created_at).toLocaleDateString()}</TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </Card>
    </div>
  );
}
