/**
 * Admin Gamification Analytics
 * Data-focused view of gamification stats and badge distribution.
 * Parity: PHP Admin\GamificationController@analytics
 */

import { useState, useCallback, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, CardHeader, Button, Spinner } from '@heroui/react';
import { ArrowLeft, Award, Users, Zap, Target } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminGamification } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';
import type { GamificationStats, BadgeDefinition } from '../../api/types';

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GamificationAnalytics() {
  usePageTitle('Admin - Gamification Analytics');
  const toast = useToast();

  const [stats, setStats] = useState<GamificationStats | null>(null);
  const [badges, setBadges] = useState<BadgeDefinition[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);

    // Load stats and badges in parallel
    const [statsRes, badgesRes] = await Promise.all([
      adminGamification.getStats(),
      adminGamification.listBadges(),
    ]);

    if (statsRes.success && statsRes.data) {
      const data = statsRes.data as unknown;
      if (data && typeof data === 'object' && 'data' in data) {
        setStats((data as { data: GamificationStats }).data);
      } else {
        setStats(data as GamificationStats);
      }
    } else {
      toast.error('Failed to load gamification stats');
    }

    if (badgesRes.success && badgesRes.data) {
      const data = badgesRes.data as unknown;
      if (Array.isArray(data)) {
        setBadges(data);
      } else if (data && typeof data === 'object' && 'data' in data) {
        setBadges((data as { data: BadgeDefinition[] }).data || []);
      }
    }

    setLoading(false);
  }, [toast]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const maxDistCount = stats?.badge_distribution?.reduce((max, b) => Math.max(max, b.count), 0) || 1;
  const builtInBadges = badges.filter((b) => b.type === 'built_in');
  const customBadges = badges.filter((b) => b.type === 'custom');

  return (
    <div>
      <PageHeader
        title="Gamification Analytics"
        description="Insights into badges, XP, and engagement"
        actions={
          <Link to="../gamification">
            <Button variant="flat" startContent={<ArrowLeft size={16} />}>
              Back to Hub
            </Button>
          </Link>
        }
      />

      {/* Stats Row */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label="Total Badges Awarded"
          value={stats?.total_badges_awarded ?? 0}
          icon={Award}
          color="primary"
          loading={loading}
        />
        <StatCard
          label="Users with Badges"
          value={stats?.active_users ?? 0}
          icon={Users}
          color="success"
          loading={loading}
        />
        <StatCard
          label="Total XP in System"
          value={stats?.total_xp_awarded ?? 0}
          icon={Zap}
          color="warning"
          loading={loading}
        />
        <StatCard
          label="Active Campaigns"
          value={stats?.active_campaigns ?? 0}
          icon={Target}
          color="secondary"
          loading={loading}
        />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Badge Distribution */}
        <Card shadow="sm">
          <CardHeader className="pb-0">
            <div>
              <h3 className="text-lg font-semibold text-foreground">Badge Distribution</h3>
              <p className="text-sm text-default-500">Top 10 most awarded badges</p>
            </div>
          </CardHeader>
          <CardBody>
            {loading ? (
              <div className="flex items-center justify-center py-12">
                <Spinner size="lg" />
              </div>
            ) : stats?.badge_distribution && stats.badge_distribution.length > 0 ? (
              <div className="space-y-3">
                {stats.badge_distribution.map((badge, index) => (
                  <div key={index} className="flex items-center gap-3">
                    <span className="w-32 truncate text-sm text-foreground font-medium" title={badge.badge_name}>
                      {badge.badge_name}
                    </span>
                    <div className="flex-1 h-6 rounded-lg bg-default-100 overflow-hidden">
                      <div
                        className="h-full rounded-lg bg-primary transition-all duration-500"
                        style={{ width: `${Math.max(2, (badge.count / maxDistCount) * 100)}%` }}
                      />
                    </div>
                    <span className="w-10 text-right text-sm font-semibold text-foreground">
                      {badge.count}
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <div className="flex flex-col items-center justify-center py-12 text-center">
                <Award size={40} className="text-default-300 mb-2" />
                <p className="text-default-500">No badges awarded yet</p>
              </div>
            )}
          </CardBody>
        </Card>

        {/* Badge Catalogue Summary */}
        <Card shadow="sm">
          <CardHeader className="pb-0">
            <div>
              <h3 className="text-lg font-semibold text-foreground">Badge Catalogue</h3>
              <p className="text-sm text-default-500">
                {badges.length} total badges ({builtInBadges.length} built-in, {customBadges.length} custom)
              </p>
            </div>
          </CardHeader>
          <CardBody>
            {loading ? (
              <div className="flex items-center justify-center py-12">
                <Spinner size="lg" />
              </div>
            ) : badges.length > 0 ? (
              <div className="space-y-2 max-h-96 overflow-y-auto">
                {badges.slice(0, 20).map((badge) => (
                  <div
                    key={badge.key}
                    className="flex items-center justify-between gap-2 rounded-lg bg-default-50 px-3 py-2"
                  >
                    <div className="flex items-center gap-2 min-w-0">
                      <Award size={16} className={badge.type === 'custom' ? 'text-success' : 'text-primary'} />
                      <span className="text-sm font-medium text-foreground truncate">{badge.name}</span>
                      {badge.type === 'custom' && (
                        <span className="text-[10px] uppercase tracking-wider text-success bg-success/10 px-1.5 py-0.5 rounded font-semibold">
                          Custom
                        </span>
                      )}
                    </div>
                    <span className="text-xs text-default-500 whitespace-nowrap">
                      {badge.awarded_count} awarded
                    </span>
                  </div>
                ))}
                {badges.length > 20 && (
                  <p className="text-center text-xs text-default-400 pt-2">
                    and {badges.length - 20} more...
                  </p>
                )}
              </div>
            ) : (
              <div className="flex flex-col items-center justify-center py-12 text-center">
                <Award size={40} className="text-default-300 mb-2" />
                <p className="text-default-500">No badges defined</p>
              </div>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default GamificationAnalytics;
