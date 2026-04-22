// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Gamification Hub
 * Dashboard overview of badges, XP, campaigns, and quick links.
 * Parity: PHP Admin\GamificationController@index
 */

import { useState, useCallback, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, CardHeader, Button, Spinner, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, Select, SelectItem, Textarea, useDisclosure } from '@heroui/react';
import Award from 'lucide-react/icons/award';
import Users from 'lucide-react/icons/users';
import Zap from 'lucide-react/icons/zap';
import Target from 'lucide-react/icons/target';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ArrowRight from 'lucide-react/icons/arrow-right';
import Megaphone from 'lucide-react/icons/megaphone';
import BarChart3 from 'lucide-react/icons/chart-column';
import Settings2 from 'lucide-react/icons/settings-2';
import Gift from 'lucide-react/icons/gift';
import { usePageTitle } from '@/hooks';
import { useToast, useTenant } from '@/contexts';
import { adminGamification } from '../../api/adminApi';
import { StatCard, PageHeader } from '../../components';
import type { GamificationStats, BadgeConfigEntry } from '../../api/types';

import { useTranslation } from 'react-i18next';
// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function GamificationHub() {
  const { t } = useTranslation('admin');
  usePageTitle("Gamification");
  const toast = useToast();
  const { tenantPath } = useTenant();

  const [stats, setStats] = useState<GamificationStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [rechecking, setRechecking] = useState(false);

  // Bulk award modal state
  const { isOpen: isBulkOpen, onOpen: onBulkOpen, onClose: onBulkClose } = useDisclosure();
  const [badges, setBadges] = useState<BadgeConfigEntry[]>([]);
  const [badgesLoading, setBadgesLoading] = useState(false);
  const [selectedBadge, setSelectedBadge] = useState('');
  const [userIdsText, setUserIdsText] = useState('');
  const [awarding, setAwarding] = useState(false);

  const loadStats = useCallback(async () => {
    setLoading(true);
    const res = await adminGamification.getStats();
    if (res.success && res.data) {
      setStats(res.data as GamificationStats);
    } else {
      toast.error("Failed to load gamification stats");
    }
    setLoading(false);
  }, [toast])


  useEffect(() => {
    loadStats();
  }, [loadStats]);

  const handleOpenBulkAward = async () => {
    onBulkOpen();
    if (badges.length === 0) {
      setBadgesLoading(true);
      const res = await adminGamification.getBadgeConfig();
      if (res.success && res.data) {
        setBadges((res.data as BadgeConfigEntry[]).filter((b) => b.is_enabled));
      }
      setBadgesLoading(false);
    }
  };

  const handleBulkAward = async () => {
    if (!selectedBadge) {
      toast.error("Bulk Award Select Badge");
      return;
    }
    const ids = userIdsText
      .split(/[\n,]+/)
      .map((s) => parseInt(s.trim(), 10))
      .filter((n) => !isNaN(n) && n > 0);
    if (ids.length === 0) {
      toast.error("Bulk Award No Users");
      return;
    }
    setAwarding(true);
    const res = await adminGamification.bulkAward(selectedBadge, ids);
    setAwarding(false);
    if (res.success) {
      const awarded = (res.data as { awarded?: number })?.awarded ?? ids.length;
      toast.success(`Bulk Award succeeded`);
      onBulkClose();
      setSelectedBadge('');
      setUserIdsText('');
    } else {
      toast.error(res.error || "Bulk Award failed");
    }
  };

  const handleRecheckAll = async () => {
    setRechecking(true);
    const res = await adminGamification.recheckAll();
    if (res.success) {
      const data = res.data as { users_checked?: number; message?: string } | undefined;
      toast.success(data?.message || "Badge recheck completed");
      loadStats();
    } else {
      toast.error(res.error || "Badge recheck failed");
    }
    setRechecking(false);
  };

  // Find the max value for distribution chart scaling
  const maxDistCount = stats?.badge_distribution?.reduce((max, b) => Math.max(max, b.count), 0) || 1;

  return (
    <div>
      <PageHeader
        title={"Gamification Hub"}
        description={"Manage badges, campaigns, and gamification settings"}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              color="secondary"
              startContent={<Gift size={16} />}
              onPress={handleOpenBulkAward}
            >
              {"Bulk Award"}
            </Button>
            <Button
              color="primary"
              startContent={<RefreshCw size={16} />}
              onPress={handleRecheckAll}
              isLoading={rechecking}
            >
              {"Recheck All Badges"}
            </Button>
          </div>
        }
      />

      {/* Stats Row */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <StatCard
          label={"Total Badges Awarded"}
          value={stats?.total_badges_awarded ?? 0}
          icon={Award}
          color="primary"
          loading={loading}
        />
        <StatCard
          label={"Active Users"}
          value={stats?.active_users ?? 0}
          icon={Users}
          color="success"
          loading={loading}
        />
        <StatCard
          label={"Total XP Awarded"}
          value={stats?.total_xp_awarded ?? 0}
          icon={Zap}
          color="warning"
          loading={loading}
        />
        <StatCard
          label={"Active Campaigns"}
          value={stats?.active_campaigns ?? 0}
          icon={Target}
          color="secondary"
          loading={loading}
        />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Badge Distribution Chart */}
        <Card shadow="sm" className="lg:col-span-2">
          <CardHeader className="flex items-center justify-between pb-0">
            <div>
              <h3 className="text-lg font-semibold text-foreground">{"Badge Distribution"}</h3>
              <p className="text-sm text-default-500">{"Top 10 Badges"}</p>
            </div>
          </CardHeader>
          <CardBody>
            {loading ? (
              <div className="flex items-center justify-center py-12">
                <Spinner size="lg" />
              </div>
            ) : stats?.badge_distribution && stats.badge_distribution.length > 0 ? (
              <div className="space-y-3">
                {stats.badge_distribution.map((badge) => (
                  <div key={badge.badge_name} className="flex items-center gap-3">
                    <span className="w-36 truncate text-sm text-foreground font-medium" title={badge.badge_name}>
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
                <p className="text-default-500">{"No badges awarded"}</p>
              </div>
            )}
          </CardBody>
        </Card>

        {/* Quick Links */}
        <Card shadow="sm">
          <CardHeader className="pb-0">
            <h3 className="text-lg font-semibold text-foreground">{"Quick Links"}</h3>
          </CardHeader>
          <CardBody className="gap-3">
            <Link to={tenantPath("/admin/gamification/campaigns")} className="block">
              <Card shadow="none" className="bg-default-50 hover:bg-default-100 transition-colors cursor-pointer">
                <CardBody className="flex flex-row items-center gap-3 p-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Megaphone size={20} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-foreground">{"Campaigns"}</p>
                    <p className="text-xs text-default-500">{"Manage Badge Campaigns"}</p>
                  </div>
                  <ArrowRight size={16} className="text-default-400" />
                </CardBody>
              </Card>
            </Link>

            <Link to={tenantPath("/admin/custom-badges")} className="block">
              <Card shadow="none" className="bg-default-50 hover:bg-default-100 transition-colors cursor-pointer">
                <CardBody className="flex flex-row items-center gap-3 p-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success">
                    <Award size={20} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-foreground">{"Custom Badges"}</p>
                    <p className="text-xs text-default-500">{"Create and Manage Badges"}</p>
                  </div>
                  <ArrowRight size={16} className="text-default-400" />
                </CardBody>
              </Card>
            </Link>

            <Link to={tenantPath("/admin/gamification/badge-config")} className="block">
              <Card shadow="none" className="bg-default-50 hover:bg-default-100 transition-colors cursor-pointer">
                <CardBody className="flex flex-row items-center gap-3 p-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-secondary/10 text-secondary">
                    <Settings2 size={20} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-foreground">{t('gamification.badge_configuration', 'Badge Configuration')}</p>
                    <p className="text-xs text-default-500">{t('gamification.configure_badge_availability', 'Enable, disable & customize badges')}</p>
                  </div>
                  <ArrowRight size={16} className="text-default-400" />
                </CardBody>
              </Card>
            </Link>

            <Link to={tenantPath("/admin/gamification/analytics")} className="block">
              <Card shadow="none" className="bg-default-50 hover:bg-default-100 transition-colors cursor-pointer">
                <CardBody className="flex flex-row items-center gap-3 p-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-warning/10 text-warning">
                    <BarChart3 size={20} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-foreground">{"Analytics"}</p>
                    <p className="text-xs text-default-500">{"Gamification Insights"}</p>
                  </div>
                  <ArrowRight size={16} className="text-default-400" />
                </CardBody>
              </Card>
            </Link>
          </CardBody>
        </Card>
      </div>

      {/* Bulk Badge Award Modal */}
      <Modal isOpen={isBulkOpen} onClose={onBulkClose} size="md">
        <ModalContent>
          <ModalHeader>{"Bulk Award Modal"}</ModalHeader>
          <ModalBody className="gap-4">
            <p className="text-sm text-default-500">
              {"Bulk Award Modal."}
            </p>
            {badgesLoading ? (
              <div className="flex justify-center py-4"><Spinner /></div>
            ) : (
              <Select
                label={"Bulk Award Badge"}
                placeholder={"Enter bulk award badge..."}
                selectedKeys={selectedBadge ? [selectedBadge] : []}
                onSelectionChange={(keys) => setSelectedBadge(Array.from(keys)[0] as string ?? '')}
              >
                {badges.map((b) => (
                  <SelectItem key={b.key}>{b.name}</SelectItem>
                ))}
              </Select>
            )}
            <Textarea
              label={"Bulk Award Users"}
              placeholder={"Enter bulk award users..."}
              value={userIdsText}
              onChange={(e) => setUserIdsText(e.target.value)}
              minRows={4}
              description={"Bulk Award Users."}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onBulkClose}>
              {"Cancel"}
            </Button>
            <Button color="secondary" onPress={handleBulkAward} isLoading={awarding}>
              {"Bulk Award Submit"}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default GamificationHub;
