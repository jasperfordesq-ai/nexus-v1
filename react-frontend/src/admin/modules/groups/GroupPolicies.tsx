// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Switch,
  Input,
  Divider,
} from '@heroui/react';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts/ToastContext';
import { adminGroups } from '@/admin/api/adminApi';
import type { GroupPolicy } from '@/admin/api/types';

interface GroupPoliciesProps {
  isOpen: boolean;
  onClose: () => void;
  typeId: number;
  typeName: string;
}

interface PolicySection {
  category: string;
  title: string;
  policies: GroupPolicy[];
}

function buildPolicySections(
  data: GroupPolicy[],
  t: (key: string) => string,
): PolicySection[] {
  const categoryMap = new Map<string, GroupPolicy[]>();

  data.forEach((policy) => {
    if (!categoryMap.has(policy.category)) {
      categoryMap.set(policy.category, []);
    }
    categoryMap.get(policy.category)!.push(policy);
  });

  return [
    {
      category: 'features',
      title: t('groups.policy_features'),
      policies: categoryMap.get('features') || [],
    },
    {
      category: 'moderation',
      title: t('groups.policy_moderation'),
      policies: categoryMap.get('moderation') || [],
    },
    {
      category: 'membership',
      title: t('groups.policy_membership'),
      policies: categoryMap.get('membership') || [],
    },
    {
      category: 'creation',
      title: t('groups.policy_creation'),
      policies: categoryMap.get('creation') || [],
    },
    {
      category: 'content',
      title: t('groups.policy_content'),
      policies: categoryMap.get('content') || [],
    },
    {
      category: 'notifications',
      title: t('groups.policy_notifications'),
      policies: categoryMap.get('notifications') || [],
    },
  ].filter((section) => section.policies.length > 0);
}

export default function GroupPolicies({
  isOpen, onClose, typeId, typeName }: GroupPoliciesProps) {
  const { t } = useTranslation('admin');
  const { success, error } = useToast();
  const [loading, setLoading] = useState(true);
  const [policies, setPolicies] = useState<GroupPolicy[]>([]);
  const [sections, setSections] = useState<PolicySection[]>([]);

  const loadPolicies = useCallback(async () => {
    try {
      setLoading(true);
      const response = await adminGroups.getPolicies(typeId);
      const data = (response.data as GroupPolicy[]) || [];
      setPolicies(data);
      setSections(buildPolicySections(data, t));
    } catch {
      error(t('groups.failed_to_load_policies'));
    } finally {
      setLoading(false);
    }
  }, [typeId, error, t]);

  useEffect(() => {
    if (isOpen) {
      loadPolicies();
    }
  }, [isOpen, loadPolicies]);

  const handlePolicyChange = async (policy: GroupPolicy, newValue: string | number | boolean) => {
    try {
      await adminGroups.setPolicy(typeId, policy.key, newValue);
      success(t('groups.policy_updated'));

      // Update local state
      const updatedPolicies = policies.map((p) =>
        p.key === policy.key ? { ...p, value: newValue } : p
      );
      setPolicies(updatedPolicies);
      setSections(buildPolicySections(updatedPolicies, t));
    } catch {
      error(t('groups.failed_to_update_policy'));
    }
  };

  const renderPolicyControl = (policy: GroupPolicy) => {
    switch (policy.type) {
      case 'boolean':
        return (
          <Switch
            isSelected={Boolean(policy.value)}
            onValueChange={(checked) => handlePolicyChange(policy, checked)}
          />
        );

      case 'number':
        return (
          <Input
            type="number"
            size="sm"
            className="w-32"
            aria-label={policy.label || policy.key}
            value={String(policy.value)}
            onValueChange={(value) => handlePolicyChange(policy, Number(value))}
          />
        );

      case 'string':
        return (
          <Input
            type="text"
            size="sm"
            className="w-48"
            aria-label={policy.label || policy.key}
            value={String(policy.value)}
            onValueChange={(value) => handlePolicyChange(policy, value)}
          />
        );

      default:
        return null;
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="2xl" scrollBehavior="inside">
      <ModalContent>
        <ModalHeader>
          <div>
            <div className="text-lg font-semibold">{t('groups.group_policies')}</div>
            <div className="text-sm font-normal text-gray-500 mt-1">{typeName}</div>
          </div>
        </ModalHeader>
        <ModalBody>
          {loading ? (
            <div className="text-center py-8 text-gray-500">{t('groups.loading_policies')}</div>
          ) : sections.length === 0 ? (
            <div className="text-center py-8 text-gray-500">{t('groups.no_policies_configured')}</div>
          ) : (
            <div className="space-y-6">
              {sections.map((section) => (
                <div key={section.category}>
                  <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                    {section.title}
                  </h3>
                  <div className="space-y-4">
                    {section.policies.map((policy) => (
                      <div
                        key={policy.key}
                        className="flex items-center justify-between py-2 px-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
                      >
                        <div className="flex-1">
                          <div className="font-medium text-sm">{policy.label}</div>
                          {policy.description && (
                            <div className="text-xs text-gray-500 mt-1">{policy.description}</div>
                          )}
                        </div>
                        <div className="flex items-center gap-2">
                          {renderPolicyControl(policy)}
                        </div>
                      </div>
                    ))}
                  </div>
                  <Divider className="mt-4" />
                </div>
              ))}
            </div>
          )}
        </ModalBody>
        <ModalFooter>
          <Button variant="light" onPress={onClose}>
            {t('close')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
