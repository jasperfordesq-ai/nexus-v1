// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { Card, CardBody, Table, TableHeader, TableColumn, TableBody, TableRow, TableCell, Button, Switch, Progress } from '@heroui/react';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Star from 'lucide-react/icons/star';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { adminGroups } from '@/admin/api/adminApi';
import type { FeaturedGroup } from '@/admin/api/types';

export default function GroupRanking() {
  const { t } = useTranslation('admin');
  usePageTitle(t('groups.page_title'));
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
      error(t('groups.failed_to_load_featured_groups'));
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
      success(t('groups.rankings_updated'));
      loadGroups();
    } catch {
      error(t('groups.failed_to_update_rankings'));
    } finally {
      setUpdating(false);
    }
  };

  const handleToggleFeatured = async (groupId: number) => {
    try {
      await adminGroups.toggleFeatured(groupId);
      success(t('groups.featured_status_updated'));
      loadGroups();
    } catch {
      error(t('groups.failed_to_update_featured_status'));
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">{t('groups.group_ranking_title')}</h1>
          <p className="text-sm text-default-500 mt-1">
            {t('groups.group_ranking_desc')}
          </p>
        </div>
        <Button
          color="primary"
          startContent={<RefreshCw className="w-4 h-4" />}
          onPress={handleUpdateRankings}
          isLoading={updating}
        >
          {t('groups.auto_update_rankings')}
        </Button>
      </div>

      <Card shadow="sm">
        <CardBody className="p-4">
        <Table aria-label={t('groups.label_featured_groups_table')}>
          <TableHeader>
            <TableColumn>{t('groups.col_group')}</TableColumn>
            <TableColumn>{t('groups.col_members')}</TableColumn>
            <TableColumn>{t('groups.col_engagement')}</TableColumn>
            <TableColumn>{t('groups.col_geo_diversity')}</TableColumn>
            <TableColumn>{t('groups.col_total_score')}</TableColumn>
            <TableColumn>{t('groups.col_featured')}</TableColumn>
          </TableHeader>
          <TableBody emptyContent={loading ? t('groups.loading') : t('groups.no_groups_found')}>
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
                    <Progress aria-label={t('groups.col_engagement')} value={Math.min(100, Number(group.engagement_score ?? 0))} size="sm" className="w-16" color="primary" />
                    <span className="text-sm">{Number(group.engagement_score ?? 0).toFixed(0)}</span>
                  </div>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Progress aria-label={t('groups.col_geo_diversity')} value={Math.min(100, Number(group.geographic_diversity ?? 0))} size="sm" className="w-16" color="success" />
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
        </CardBody>
      </Card>

      <Card shadow="sm">
        <CardBody className="p-6">
        <h2 className="text-lg font-semibold mb-3 text-foreground">{t('groups.ranking_algorithm_title')}</h2>
        <div className="space-y-2 text-sm text-default-500">
          <p>
            <strong className="text-foreground">{t('groups.total_score_formula_label')}</strong>{' '}
            {t('groups.total_score_formula')}
          </p>
          <p><strong className="text-foreground">{t('groups.engagement_score_label')}</strong>{' '}{t('groups.engagement_score_desc')}</p>
          <p><strong className="text-foreground">{t('groups.geographic_diversity_label')}</strong>{' '}{t('groups.geographic_diversity_desc')}</p>
          <p className="text-xs mt-4">
            {t('groups.ranking_algorithm_note')}
          </p>
        </div>
        </CardBody>
      </Card>
    </div>
  );
}
