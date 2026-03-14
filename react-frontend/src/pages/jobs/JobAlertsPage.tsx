// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Job Alerts Page (J6) - Manage job alert subscriptions
 *
 * Features:
 * - List active/paused alerts
 * - Create new alert with keyword, category, type, commitment, location filters
 * - Pause/resume and delete alerts
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Input,
  Select,
  SelectItem,
  Switch,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import {
  Bell,
  Plus,
  ArrowLeft,
  Trash2,
  Pause,
  Play,
  MapPin,
  Tag,
  Briefcase,
  Wifi,
  AlertTriangle,
  RefreshCw,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';

interface JobAlert {
  id: number;
  user_id: number;
  tenant_id: number;
  keywords: string | null;
  categories: string | null;
  type: string | null;
  commitment: string | null;
  location: string | null;
  is_remote_only: boolean;
  is_active: boolean;
  last_notified_at: string | null;
  created_at: string;
}

const JOB_TYPES = ['paid', 'volunteer', 'timebank'] as const;
const COMMITMENT_TYPES = ['full_time', 'part_time', 'flexible', 'one_off'] as const;

export function JobAlertsPage() {
  const { t } = useTranslation('jobs');
  usePageTitle(t('alerts.title'));
  const { tenantPath } = useTenant();
  const toast = useToast();
  const createModal = useDisclosure();

  const [alerts, setAlerts] = useState<JobAlert[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Create form state
  const [keywords, setKeywords] = useState('');
  const [categories, setCategories] = useState('');
  const [alertType, setAlertType] = useState('');
  const [alertCommitment, setAlertCommitment] = useState('');
  const [alertLocation, setAlertLocation] = useState('');
  const [isRemoteOnly, setIsRemoteOnly] = useState(false);
  const [isCreating, setIsCreating] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<number | null>(null);

  const loadAlerts = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await api.get<JobAlert[]>('/v2/jobs/alerts');
      if (response.success && response.data) {
        setAlerts(response.data);
      }
    } catch (err) {
      logError('Failed to load job alerts', err);
      setError(t('alerts.load_error', 'Failed to load alerts. Please try again.'));
    } finally {
      setIsLoading(false);
    }
  }, [t]);

  useEffect(() => {
    loadAlerts();
  }, [loadAlerts]);

  const handleCreate = async () => {
    setIsCreating(true);
    try {
      const payload: Record<string, unknown> = {};
      if (keywords.trim()) payload.keywords = keywords.trim();
      if (categories.trim()) payload.categories = categories.trim();
      if (alertType) payload.type = alertType;
      if (alertCommitment) payload.commitment = alertCommitment;
      if (alertLocation.trim()) payload.location = alertLocation.trim();
      if (isRemoteOnly) payload.is_remote_only = true;

      const response = await api.post('/v2/jobs/alerts', payload);
      if (response.success) {
        toast.success(t('alerts.create_success'));
        createModal.onClose();
        resetForm();
        loadAlerts();
      } else {
        toast.error(t('alerts.create_error'));
      }
    } catch (err) {
      logError('Failed to create alert', err);
      toast.error(t('alerts.create_error'));
    } finally {
      setIsCreating(false);
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;

    try {
      await api.delete(`/v2/jobs/alerts/${deleteTarget}`);
      toast.success(t('alerts.delete_success'));
      setAlerts((prev) => prev.filter((a) => a.id !== deleteTarget));
    } catch (err) {
      logError('Failed to delete alert', err);
      toast.error(t('alerts.delete_error'));
    } finally {
      setDeleteTarget(null);
    }
  };

  const handleTogglePause = async (alertId: number, isActive: boolean) => {
    try {
      if (isActive) {
        await api.put(`/v2/jobs/alerts/${alertId}/unsubscribe`, {});
        toast.success(t('alerts.unsubscribe_success'));
      } else {
        await api.put(`/v2/jobs/alerts/${alertId}/resubscribe`, {});
        toast.success(t('alerts.resubscribe_success'));
      }
      loadAlerts();
    } catch (err) {
      logError('Failed to toggle alert', err);
      toast.error(t('alerts.toggle_error'));
    }
  };

  const resetForm = () => {
    setKeywords('');
    setCategories('');
    setAlertType('');
    setAlertCommitment('');
    setAlertLocation('');
    setIsRemoteOnly(false);
  };

  return (
    <div className="space-y-6 max-w-2xl mx-auto">
      {/* Back nav */}
      <Link
        to={tenantPath('/jobs')}
        className="inline-flex items-center gap-2 text-theme-muted hover:text-theme-primary transition-colors"
      >
        <ArrowLeft className="w-4 h-4" aria-hidden="true" />
        {t('title')}
      </Link>

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Bell className="w-7 h-7 text-blue-400" aria-hidden="true" />
            {t('alerts.title')}
          </h1>
          <p className="text-theme-muted mt-1">{t('alerts.subtitle')}</p>
        </div>
        <Button
          className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
          startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
          onPress={createModal.onOpen}
        >
          {t('alerts.create')}
        </Button>
      </div>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('alerts.load_error', 'Failed to load alerts')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={loadAlerts}
          >
            {t('alerts.retry', 'Retry')}
          </Button>
        </GlassCard>
      )}

      {/* Alerts List */}
      {isLoading ? (
        <div className="space-y-4">
          {[1, 2].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="h-4 bg-theme-hover rounded w-1/2 mb-2" />
              <div className="h-3 bg-theme-hover rounded w-3/4" />
            </GlassCard>
          ))}
        </div>
      ) : !error && alerts.length === 0 ? (
        <EmptyState
          icon={<Bell className="w-12 h-12" aria-hidden="true" />}
          title={t('alerts.empty_title')}
          description={t('alerts.empty_description')}
          action={
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
              onPress={createModal.onOpen}
            >
              {t('alerts.create')}
            </Button>
          }
        />
      ) : (
        <div className="space-y-4">
          {alerts.map((alert) => (
            <motion.div
              key={alert.id}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
            >
              <GlassCard className="p-5">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap mb-2">
                      <Chip
                        size="sm"
                        variant="flat"
                        color={alert.is_active ? 'success' : 'default'}
                      >
                        {alert.is_active ? t('alerts.active') : t('alerts.paused')}
                      </Chip>
                      {alert.type && (
                        <Chip size="sm" variant="flat" color="primary">
                          {t(`type.${alert.type}`)}
                        </Chip>
                      )}
                      {alert.commitment && (
                        <Chip size="sm" variant="flat" color="secondary">
                          {t(`commitment.${alert.commitment}`)}
                        </Chip>
                      )}
                      {alert.is_remote_only && (
                        <Chip size="sm" variant="flat" color="default" startContent={<Wifi className="w-3 h-3" />}>
                          {t('remote')}
                        </Chip>
                      )}
                    </div>

                    <div className="flex flex-wrap gap-3 text-sm text-theme-muted">
                      {alert.keywords && (
                        <span className="flex items-center gap-1">
                          <Tag className="w-3.5 h-3.5" aria-hidden="true" />
                          {alert.keywords}
                        </span>
                      )}
                      {alert.categories && (
                        <span className="flex items-center gap-1">
                          <Briefcase className="w-3.5 h-3.5" aria-hidden="true" />
                          {alert.categories}
                        </span>
                      )}
                      {alert.location && (
                        <span className="flex items-center gap-1">
                          <MapPin className="w-3.5 h-3.5" aria-hidden="true" />
                          {alert.location}
                        </span>
                      )}
                    </div>

                    <p className="text-xs text-theme-subtle mt-2">
                      {t('alerts.created_date', 'Created {{date}}', { date: new Date(alert.created_at).toLocaleDateString() })}
                      {alert.last_notified_at && (
                        <> &middot; {t('alerts.last_notified', 'Last notification {{date}}', { date: new Date(alert.last_notified_at).toLocaleDateString() })}</>
                      )}
                    </p>
                  </div>

                  <div className="flex gap-1">
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      className="text-theme-muted"
                      onPress={() => handleTogglePause(alert.id, alert.is_active)}
                      aria-label={alert.is_active ? t('alerts.pause') : t('alerts.resume')}
                    >
                      {alert.is_active ? (
                        <Pause className="w-4 h-4" />
                      ) : (
                        <Play className="w-4 h-4" />
                      )}
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      isIconOnly
                      color="danger"
                      onPress={() => setDeleteTarget(alert.id)}
                      aria-label={t('alerts.delete')}
                    >
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  </div>
                </div>
              </GlassCard>
            </motion.div>
          ))}
        </div>
      )}

      {/* Create Alert Modal */}
      <Modal isOpen={createModal.isOpen} onOpenChange={createModal.onOpenChange} size="lg">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>
                <div className="flex items-center gap-2">
                  <Bell className="w-5 h-5 text-primary" aria-hidden="true" />
                  {t('alerts.create')}
                </div>
              </ModalHeader>
              <ModalBody>
                <div className="space-y-4">
                  <Input
                    label={t('alerts.keywords_label')}
                    placeholder={t('alerts.keywords_placeholder')}
                    value={keywords}
                    onChange={(e) => setKeywords(e.target.value)}
                    description={t('alerts.keywords_description')}
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                    }}
                  />

                  <Input
                    label={t('alerts.categories_label')}
                    placeholder={t('alerts.categories_placeholder')}
                    value={categories}
                    onChange={(e) => setCategories(e.target.value)}
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                    }}
                  />

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Select
                      label={t('alerts.type_label')}
                      selectedKeys={alertType ? [alertType] : []}
                      onChange={(e) => setAlertType(e.target.value)}
                      placeholder={t('alerts.any')}
                      classNames={{
                        trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                        value: 'text-theme-primary',
                      }}
                    >
                      {JOB_TYPES.map((type) => (
                        <SelectItem key={type}>{t(`type.${type}`)}</SelectItem>
                      ))}
                    </Select>

                    <Select
                      label={t('alerts.commitment_label')}
                      selectedKeys={alertCommitment ? [alertCommitment] : []}
                      onChange={(e) => setAlertCommitment(e.target.value)}
                      placeholder={t('alerts.any')}
                      classNames={{
                        trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                        value: 'text-theme-primary',
                      }}
                    >
                      {COMMITMENT_TYPES.map((type) => (
                        <SelectItem key={type}>{t(`commitment.${type}`)}</SelectItem>
                      ))}
                    </Select>
                  </div>

                  <Input
                    label={t('alerts.location_label')}
                    placeholder={t('alerts.location_placeholder')}
                    value={alertLocation}
                    onChange={(e) => setAlertLocation(e.target.value)}
                    classNames={{
                      input: 'bg-transparent text-theme-primary',
                      inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                    }}
                  />

                  <Switch
                    isSelected={isRemoteOnly}
                    onValueChange={setIsRemoteOnly}
                    classNames={{ label: 'text-theme-primary text-sm' }}
                  >
                    <div>
                      <p className="text-sm text-theme-primary">{t('alerts.remote_only')}</p>
                    </div>
                  </Switch>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  {t('apply.cancel')}
                </Button>
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  onPress={handleCreate}
                  isLoading={isCreating}
                >
                  {t('alerts.create')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        isOpen={deleteTarget !== null}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="text-theme-primary">{t('alerts.delete', 'Delete alert')}</ModalHeader>
              <ModalBody>
                <p className="text-theme-secondary">
                  {t('alerts.delete_confirm', 'Are you sure you want to delete this alert? This cannot be undone.')}
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>{t('apply.cancel', 'Cancel')}</Button>
                <Button color="danger" onPress={handleDelete}>{t('alerts.delete', 'Delete alert')}</Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default JobAlertsPage;
