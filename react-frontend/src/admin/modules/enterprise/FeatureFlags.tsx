// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Feature Flags
 * Toggle features and modules for the current tenant.
 */

import { useEffect, useState, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Spinner,
  Switch,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import { RefreshCw, ToggleLeft, Boxes, AlertTriangle } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { FeatureFlags as FeatureFlagsType } from '../../api/types';

import { useTranslation } from 'react-i18next';

const FLAG_DESCRIPTIONS: Record<string, string> = {
  // Features
  events: 'Community events calendar with RSVP and attendance tracking',
  groups: 'Community groups and hubs for members to organize around interests',
  gamification: 'Badges, XP points, leaderboards, and achievement campaigns',
  goals: 'Community goals that members can contribute to collectively',
  blog: 'Community blog with articles, categories, and author management',
  resources: 'Knowledge base articles and resource library',
  volunteering: 'Volunteer opportunities and organization management',
  exchange_workflow: 'Broker-controlled exchange workflow with approval steps',
  organisations: 'Organization profiles and organizational wallets',
  federation: 'Multi-community federation and partner directory',
  connections: 'Member-to-member connection requests and network',
  reviews: 'Member reviews and ratings after exchanges',
  polls: 'Community polls and surveys',
  job_vacancies: 'Job vacancy board with applications and CV uploads',
  ideation_challenges: 'Innovation challenges and idea submissions',
  direct_messaging: 'Direct messaging between members',
  group_exchanges: 'Time credit exchanges within groups',
  search: 'Full-text search across listings, members, and content',
  ai_chat: 'AI-powered chat assistant for members',
  // Modules
  listings:
    'Core listing/service marketplace — members cannot browse or post services without this',
  wallet: 'Time credit wallet — all transactions and balances depend on this',
  messages: 'Messaging system — inbox and conversations',
  dashboard: 'Member dashboard — the main landing page after login',
  feed: 'Activity feed — community updates and social posts',
  notifications: 'Notification system — alerts, badges, and push notifications',
  profile: 'Member profiles — viewing and editing profile information',
  settings: 'Account settings — password, preferences, security options',
};

const CRITICAL_FLAGS = new Set([
  'listings',
  'wallet',
  'messages',
  'dashboard',
  'feed',
  'notifications',
  'profile',
  'settings',
]);

function formatKey(key: string): string {
  return key
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

export function FeatureFlags() {
  const { t } = useTranslation('admin');
  usePageTitle("Enterprise");
  const toast = useToast();

  const [data, setData] = useState<FeatureFlagsType | null>(null);
  const [loading, setLoading] = useState(true);
  const [togglingKeys, setTogglingKeys] = useState<Set<string>>(new Set());
  const [confirmModal, setConfirmModal] = useState<{
    key: string;
    type: 'feature' | 'module';
  } | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getFeatureFlags();
      if (res.success && res.data) {
        setData(res.data as unknown as FeatureFlagsType);
      }
    } catch {
      toast.error("Failed to load feature flags");
    } finally {
      setLoading(false);
    }
  }, [toast, t])

  useEffect(() => {
    loadData();
  }, [loadData]);

  const executeToggle = async (key: string, value: boolean, type: 'feature' | 'module') => {
    const toggleKey = `${type}:${key}`;
    setTogglingKeys((prev) => new Set(prev).add(toggleKey));

    // Optimistic update
    setData((prev) => {
      if (!prev) return prev;
      const section = type === 'feature' ? 'features' : 'modules';
      return {
        ...prev,
        [section]: { ...prev[section], [key]: value },
      };
    });

    try {
      // NOTE: tenant scope is enforced server-side via TenantContext (auth middleware).
      // We intentionally do NOT send a tenant_id from the client — any value here
      // could be spoofed; the backend always derives scope from the authenticated user.
      const res = await adminEnterprise.updateFeatureFlag({ key, value, type });
      if (res.success) {
        toast.success(`Feature Flag Toggled`);
      } else {
        // Revert on failure
        setData((prev) => {
          if (!prev) return prev;
          const section = type === 'feature' ? 'features' : 'modules';
          return {
            ...prev,
            [section]: { ...prev[section], [key]: !value },
          };
        });
        toast.error(`Failed to update feature flag`);
      }
    } catch {
      // Revert on error
      setData((prev) => {
        if (!prev) return prev;
        const section = type === 'feature' ? 'features' : 'modules';
        return {
          ...prev,
          [section]: { ...prev[section], [key]: !value },
        };
      });
      toast.error(`Failed to update feature flag`);
    } finally {
      setTogglingKeys((prev) => {
        const next = new Set(prev);
        next.delete(toggleKey);
        return next;
      });
    }
  };

  const handleToggle = (key: string, value: boolean, type: 'feature' | 'module') => {
    // If disabling a critical flag, show confirmation modal first
    if (!value && CRITICAL_FLAGS.has(key)) {
      setConfirmModal({ key, type });
      return;
    }
    executeToggle(key, value, type);
  };

  const handleConfirmDisable = () => {
    if (confirmModal) {
      executeToggle(confirmModal.key, false, confirmModal.type);
      setConfirmModal(null);
    }
  };

  const renderSection = (
    title: string,
    icon: React.ReactNode,
    items: Record<string, boolean>,
    type: 'feature' | 'module',
  ) => {
    const sortedKeys = Object.keys(items).sort();

    return (
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2 px-6 pt-5 pb-0">
          {icon}
          <h3 className="text-base font-semibold">{title}</h3>
        </CardHeader>
        <CardBody className="px-6 pb-5">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {sortedKeys.map((key) => {
              const toggleKey = `${type}:${key}`;
              const isToggling = togglingKeys.has(toggleKey);

              const isCritical = CRITICAL_FLAGS.has(key);
              const description = FLAG_DESCRIPTIONS[key];

              return (
                <div
                  key={key}
                  className="flex items-center justify-between gap-3 rounded-lg border border-divider p-3"
                >
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-1.5">
                      <span className="text-sm font-medium text-foreground">
                        {formatKey(key)}
                      </span>
                      {isCritical && (
                        <AlertTriangle size={14} className="shrink-0 text-warning" />
                      )}
                    </div>
                    {description && (
                      <p className="mt-0.5 text-xs text-default-400">{description}</p>
                    )}
                    {isCritical && (
                      <p className="mt-0.5 text-xs font-medium text-warning">{"Core Module"}</p>
                    )}
                  </div>
                  <Switch
                    size="sm"
                    isSelected={items[key]}
                    isDisabled={isToggling}
                    onValueChange={(val) => handleToggle(key, val, type)}
                    aria-label={`Toggle ${formatKey(key)}`}
                  />
                </div>
              );
            })}
          </div>
        </CardBody>
      </Card>
    );
  };

  return (
    <div>
      <PageHeader
        title={"Feature Flags Page"}
        description={"Feature Flags Page."}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadData}
            isLoading={loading}
            size="sm"
          >
            {"Refresh"}
          </Button>
        }
      />

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : data ? (
        <div className="space-y-6">
          {renderSection(
            'Features',
            <ToggleLeft size={18} className="text-primary" />,
            data.features,
            'feature',
          )}
          {renderSection(
            'Modules',
            <Boxes size={18} className="text-secondary" />,
            data.modules,
            'module',
          )}
        </div>
      ) : null}

      {/* Critical flag disable confirmation modal */}
      <Modal
        isOpen={!!confirmModal}
        onClose={() => setConfirmModal(null)}
        size="md"
      >
        <ModalContent>
          {() => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <AlertTriangle size={20} className="text-warning" />
                <span>{`Disable Feature`}</span>
              </ModalHeader>
              <ModalBody>
                <p className="text-sm text-default-600">
                  {t('enterprise.disable_feature_warning', {
                    feature: confirmModal
                      ? FLAG_DESCRIPTIONS[confirmModal.key]?.toLowerCase() ||
                        formatKey(confirmModal.key).toLowerCase()
                      : '',
                  })}
                </p>
                <div className="mt-2 rounded-lg border border-warning/30 bg-warning/10 p-3">
                  <p className="text-xs font-medium text-warning">
                    {`Core Module Warning`}
                  </p>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={() => setConfirmModal(null)}>
                  Cancel
                </Button>
                <Button color="danger" onPress={handleConfirmDisable}>
                  Disable
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default FeatureFlags;
