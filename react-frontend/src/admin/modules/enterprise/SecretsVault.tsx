// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Secrets Vault
 * Grouped view of env vars by category with rotate and test actions.
 */

import { useEffect, useState, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Spinner,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import {
  KeyRound,
  CheckCircle,
  XCircle,
  RefreshCw,
  RotateCcw,
  Plug,
  ChevronDown,
  ChevronRight,
  Database,
  Mail,
  Bot,
  Shield,
  Bell,
  HardDrive,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { SecretEntry } from '../../api/types';

import { useTranslation } from 'react-i18next';

interface CategoryDef { label: string; icon: typeof Database; color: string }

const DEFAULT_CATEGORY: CategoryDef = { label: 'Other', icon: KeyRound, color: 'text-default-500' };

const CATEGORY_CONFIG: Record<string, CategoryDef> = {
  database: { label: 'Database', icon: Database, color: 'text-primary' },
  cache: { label: 'Cache', icon: HardDrive, color: 'text-secondary' },
  push: { label: 'Push Notifications', icon: Bell, color: 'text-warning' },
  email: { label: 'Email', icon: Mail, color: 'text-success' },
  ai: { label: 'AI / ML', icon: Bot, color: 'text-primary' },
  auth: { label: 'Authentication', icon: Shield, color: 'text-danger' },
  other: DEFAULT_CATEGORY,
};

function getCategoryConfig(cat: string): CategoryDef {
  return CATEGORY_CONFIG[cat] ?? DEFAULT_CATEGORY;
}

function categorizeSecret(key: string): string {
  const k = key.toLowerCase();
  if (k.includes('db_') || k.includes('database') || k.includes('mysql') || k.includes('mariadb')) return 'database';
  if (k.includes('redis') || k.includes('cache') || k.includes('memcache')) return 'cache';
  if (k.includes('pusher') || k.includes('fcm') || k.includes('push') || k.includes('firebase')) return 'push';
  if (k.includes('mail') || k.includes('smtp') || k.includes('gmail') || k.includes('sendgrid')) return 'email';
  if (k.includes('openai') || k.includes('ai_') || k.includes('embedding') || k.includes('gpt')) return 'ai';
  if (k.includes('auth') || k.includes('jwt') || k.includes('token') || k.includes('secret') || k.includes('key') || k.includes('webauthn') || k.includes('sanctum') || k.includes('app_key')) return 'auth';
  return 'other';
}

export function SecretsVault() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));
  const toast = useToast();

  const [secrets, setSecrets] = useState<SecretEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [collapsedCategories, setCollapsedCategories] = useState<Set<string>>(new Set());
  const [rotatingKey, setRotatingKey] = useState<string | null>(null);
  const [rotateModalOpen, setRotateModalOpen] = useState(false);
  const [rotateMessage, setRotateMessage] = useState('');
  const [testingConnection, setTestingConnection] = useState(false);
  const [testResults, setTestResults] = useState<Record<string, boolean> | null>(null);
  const [testModalOpen, setTestModalOpen] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminEnterprise.getSecrets();
      if (res.success && res.data) {
        const data = res.data as unknown;
        setSecrets(Array.isArray(data) ? data : []);
      }
    } catch {
      // Silently handle
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const toggleCategory = (cat: string) => {
    setCollapsedCategories((prev) => {
      const next = new Set(prev);
      if (next.has(cat)) {
        next.delete(cat);
      } else {
        next.add(cat);
      }
      return next;
    });
  };

  const handleRotate = async (key: string) => {
    setRotatingKey(key);
    try {
      const res = await adminEnterprise.rotateSecret(key);
      if (res.success) {
        setRotateMessage(res.data?.message || t('enterprise.secret_rotation_manual_update'));
        setRotateModalOpen(true);
      } else {
        toast.error(t('enterprise.failed_to_rotate_secret'));
      }
    } catch {
      toast.error(t('enterprise.failed_to_rotate_secret'));
    } finally {
      setRotatingKey(null);
    }
  };

  const handleTestConnection = async () => {
    setTestingConnection(true);
    try {
      const res = await adminEnterprise.testVaultConnection();
      if (res.success && res.data) {
        setTestResults((res.data as unknown as { services: Record<string, boolean> }).services);
        setTestModalOpen(true);
      } else {
        toast.error(t('enterprise.connection_test_failed'));
      }
    } catch {
      toast.error(t('enterprise.connection_test_failed'));
    } finally {
      setTestingConnection(false);
    }
  };

  // Group secrets by category
  const grouped: Record<string, SecretEntry[]> = {};
  for (const secret of secrets) {
    const cat = categorizeSecret(secret.key);
    if (!grouped[cat]) grouped[cat] = [];
    grouped[cat].push(secret);
  }

  // Sort categories
  const categoryOrder = ['database', 'cache', 'push', 'email', 'ai', 'auth', 'other'];
  const sortedCategories = categoryOrder.filter((cat) => grouped[cat]?.length);

  const setCount = secrets.filter((s) => s.is_set).length;

  return (
    <div>
      <PageHeader
        title={t('enterprise.secrets_vault_title')}
        description={t('enterprise.secrets_vault_desc', { configured: setCount, total: secrets.length })}
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<Plug size={16} />}
              onPress={handleTestConnection}
              isLoading={testingConnection}
              size="sm"
            >
              {t('enterprise.btn_test_connection')}
            </Button>
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={loadData}
              isLoading={loading}
              size="sm"
            >
              {t('common.refresh')}
            </Button>
          </div>
        }
      />

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : (
        <div className="space-y-4">
          {sortedCategories.map((cat) => {
            const catConfig = getCategoryConfig(cat);
            const CategoryIcon = catConfig.icon;
            const isCollapsed = collapsedCategories.has(cat);
            const catSecrets = grouped[cat] ?? [];
            const catSetCount = catSecrets.filter((s) => s.is_set).length;

            return (
              <Card key={cat} shadow="sm">
                <CardHeader
                  className="flex items-center justify-between px-6 pt-4 pb-0 cursor-pointer"
                  onClick={() => toggleCategory(cat)}
                >
                  <div className="flex items-center gap-3">
                    {isCollapsed ? (
                      <ChevronRight size={16} className="text-default-400" />
                    ) : (
                      <ChevronDown size={16} className="text-default-400" />
                    )}
                    <CategoryIcon size={18} className={catConfig.color} />
                    <h3 className="text-base font-semibold">{t(`enterprise.category_${cat}`, catConfig.label)}</h3>
                    <Chip size="sm" variant="flat" color={catSetCount === catSecrets.length ? 'success' : 'warning'}>
                      {t('enterprise.secrets_n_of_m_set', { n: catSetCount, m: catSecrets.length })}
                    </Chip>
                  </div>
                </CardHeader>
                {!isCollapsed && (
                  <CardBody className="p-0">
                    <div className="divide-y divide-divider">
                      {catSecrets.map((secret) => (
                        <div
                          key={secret.key}
                          className="flex items-center gap-3 px-6 py-3"
                        >
                          <KeyRound size={16} className="text-default-400 shrink-0" />
                          <span className="font-mono text-sm font-medium text-foreground flex-1 min-w-0 truncate">
                            {secret.key}
                          </span>
                          <span className="text-xs text-default-400 font-mono shrink-0">
                            {secret.masked_value}
                          </span>
                          {secret.is_set ? (
                            <Chip size="sm" variant="flat" color="success" startContent={<CheckCircle size={12} />}>
                              {t('enterprise.set')}
                            </Chip>
                          ) : (
                            <Chip size="sm" variant="flat" color="danger" startContent={<XCircle size={12} />}>
                              {t('enterprise.missing')}
                            </Chip>
                          )}
                          <Button
                            size="sm"
                            variant="flat"
                            startContent={<RotateCcw size={12} />}
                            onPress={() => handleRotate(secret.key)}
                            isLoading={rotatingKey === secret.key}
                            isDisabled={!secret.is_set}
                          >
                            {t('enterprise.btn_rotate')}
                          </Button>
                        </div>
                      ))}
                    </div>
                  </CardBody>
                )}
              </Card>
            );
          })}
        </div>
      )}

      {/* Rotate Result Modal */}
      <Modal isOpen={rotateModalOpen} onClose={() => setRotateModalOpen(false)}>
        <ModalContent>
          <ModalHeader>{t('enterprise.secret_rotation_title')}</ModalHeader>
          <ModalBody>
            <p className="text-default-600">{rotateMessage}</p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setRotateModalOpen(false)}>
              {t('common.ok')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Test Connection Results Modal */}
      <Modal isOpen={testModalOpen} onClose={() => setTestModalOpen(false)}>
        <ModalContent>
          <ModalHeader>{t('enterprise.connection_test_title')}</ModalHeader>
          <ModalBody>
            {testResults && (
              <div className="space-y-3">
                {Object.entries(testResults).map(([service, ok]) => (
                  <div key={service} className="flex items-center gap-3">
                    {ok ? (
                      <CheckCircle size={18} className="text-success" />
                    ) : (
                      <XCircle size={18} className="text-danger" />
                    )}
                    <span className="text-sm font-medium text-foreground">{service}</span>
                    <Chip size="sm" variant="flat" color={ok ? 'success' : 'danger'}>
                      {ok ? t('enterprise.connected') : t('enterprise.failed')}
                    </Chip>
                  </div>
                ))}
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setTestModalOpen(false)}>
              {t('common.close')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default SecretsVault;
