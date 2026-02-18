import { useState, useEffect } from 'react';
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

export default function GroupPolicies({ isOpen, onClose, typeId, typeName }: GroupPoliciesProps) {
  const { success, error } = useToast();
  const [loading, setLoading] = useState(true);
  const [policies, setPolicies] = useState<GroupPolicy[]>([]);
  const [sections, setSections] = useState<PolicySection[]>([]);

  useEffect(() => {
    if (isOpen) {
      loadPolicies();
    }
  }, [isOpen, typeId]);

  const loadPolicies = async () => {
    try {
      setLoading(true);
      const response = await adminGroups.getPolicies(typeId);
      const data = (response.data as GroupPolicy[]) || [];
      setPolicies(data);
      organizePolicies(data);
    } catch (err) {
      error('Failed to load policies');
    } finally {
      setLoading(false);
    }
  };

  const organizePolicies = (data: GroupPolicy[]) => {
    const categoryMap = new Map<string, GroupPolicy[]>();

    data.forEach((policy) => {
      if (!categoryMap.has(policy.category)) {
        categoryMap.set(policy.category, []);
      }
      categoryMap.get(policy.category)!.push(policy);
    });

    const organized: PolicySection[] = [
      {
        category: 'features',
        title: 'Features',
        policies: categoryMap.get('features') || [],
      },
      {
        category: 'moderation',
        title: 'Moderation',
        policies: categoryMap.get('moderation') || [],
      },
      {
        category: 'membership',
        title: 'Membership',
        policies: categoryMap.get('membership') || [],
      },
      {
        category: 'creation',
        title: 'Creation',
        policies: categoryMap.get('creation') || [],
      },
      {
        category: 'content',
        title: 'Content',
        policies: categoryMap.get('content') || [],
      },
      {
        category: 'notifications',
        title: 'Notifications',
        policies: categoryMap.get('notifications') || [],
      },
    ].filter((section) => section.policies.length > 0);

    setSections(organized);
  };

  const handlePolicyChange = async (policy: GroupPolicy, newValue: string | number | boolean) => {
    try {
      await adminGroups.setPolicy(typeId, policy.key, newValue);
      success('Policy updated');

      // Update local state
      const updatedPolicies = policies.map((p) =>
        p.key === policy.key ? { ...p, value: newValue } : p
      );
      setPolicies(updatedPolicies);
      organizePolicies(updatedPolicies);
    } catch (err) {
      error('Failed to update policy');
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
            <div className="text-lg font-semibold">Group Policies</div>
            <div className="text-sm font-normal text-gray-500 mt-1">{typeName}</div>
          </div>
        </ModalHeader>
        <ModalBody>
          {loading ? (
            <div className="text-center py-8 text-gray-500">Loading policies...</div>
          ) : sections.length === 0 ? (
            <div className="text-center py-8 text-gray-500">No policies configured</div>
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
            Close
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
