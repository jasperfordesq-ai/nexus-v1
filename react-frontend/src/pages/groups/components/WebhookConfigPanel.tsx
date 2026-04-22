// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Webhook Config Panel
 * Admin panel for managing group webhooks (add, toggle, delete).
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Button,
  Chip,
  Input,
  Switch,
  Checkbox,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Spinner,
} from '@heroui/react';
import Webhook from 'lucide-react/icons/webhook';
import Plus from 'lucide-react/icons/plus';
import Trash2 from 'lucide-react/icons/trash-2';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import { api } from '@/lib/api';
import { useToast } from '@/contexts';
import { GlassCard } from '@/components/ui';
import { formatDateValue } from '@/lib/helpers';
import { useTranslation } from 'react-i18next';

interface WebhookConfigPanelProps {
  groupId: number;
  isAdmin: boolean;
}

interface WebhookItem {
  id: number;
  url: string;
  events: string[];
  is_active: boolean;
  last_fired_at: string | null;
  failure_count: number;
}

const AVAILABLE_EVENTS = [
  'member.joined',
  'member.left',
  'discussion.created',
  'post.created',
  'group.updated',
  'file.uploaded',
] as const;

export function WebhookConfigPanel({ groupId, isAdmin }: WebhookConfigPanelProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();

  const [webhooks, setWebhooks] = useState<WebhookItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);

  // Add webhook form state
  const [newUrl, setNewUrl] = useState('');
  const [newEvents, setNewEvents] = useState<string[]>([]);
  const [newSecret, setNewSecret] = useState('');
  const [creating, setCreating] = useState(false);

  const loadWebhooks = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get(`/v2/groups/${groupId}/webhooks`);
      if (res.success && res.data) {
        const payload = res.data;
        setWebhooks(Array.isArray(payload) ? payload : []);
      }
    } catch {
      toast.error(t('webhooks.load_failed', 'Failed to load webhooks'));
    } finally {
      setLoading(false);
    }
  }, [groupId, toast, t]);

  useEffect(() => {
    loadWebhooks();
  }, [loadWebhooks]);

  const handleCreate = async () => {
    if (!newUrl.trim()) {
      toast.error(t('webhooks.url_required', 'Webhook URL is required'));
      return;
    }
    if (newEvents.length === 0) {
      toast.error(t('webhooks.events_required', 'Select at least one event'));
      return;
    }

    setCreating(true);
    try {
      const body: Record<string, unknown> = { url: newUrl.trim(), events: newEvents };
      if (newSecret.trim()) {
        body.secret = newSecret.trim();
      }

      const res = await api.post(`/v2/groups/${groupId}/webhooks`, body);
      if (res.success) {
        toast.success(t('webhooks.created', 'Webhook created'));
        setModalOpen(false);
        resetForm();
        await loadWebhooks();
      } else {
        toast.error(t('webhooks.create_failed', 'Failed to create webhook'));
      }
    } catch {
      toast.error(t('webhooks.create_failed', 'Failed to create webhook'));
    } finally {
      setCreating(false);
    }
  };

  const handleToggle = async (webhookId: number, isActive: boolean) => {
    try {
      const res = await api.put(
        `/v2/groups/${groupId}/webhooks/${webhookId}/toggle`,
        { is_active: isActive }
      );
      if (res.success) {
        setWebhooks((prev) =>
          prev.map((wh) => (wh.id === webhookId ? { ...wh, is_active: isActive } : wh))
        );
      } else {
        toast.error(t('webhooks.toggle_failed', 'Failed to toggle webhook'));
      }
    } catch {
      toast.error(t('webhooks.toggle_failed', 'Failed to toggle webhook'));
    }
  };

  const handleDelete = async (webhookId: number) => {
    try {
      const res = await api.delete(`/v2/groups/${groupId}/webhooks/${webhookId}`);
      if (res.success) {
        setWebhooks((prev) => prev.filter((wh) => wh.id !== webhookId));
        toast.success(t('webhooks.deleted', 'Webhook deleted'));
      } else {
        toast.error(t('webhooks.delete_failed', 'Failed to delete webhook'));
      }
    } catch {
      toast.error(t('webhooks.delete_failed', 'Failed to delete webhook'));
    }
  };

  const toggleEvent = (event: string) => {
    setNewEvents((prev) =>
      prev.includes(event) ? prev.filter((e) => e !== event) : [...prev, event]
    );
  };

  const resetForm = () => {
    setNewUrl('');
    setNewEvents([]);
    setNewSecret('');
  };

  if (!isAdmin) return null;

  return (
    <GlassCard className="p-5 space-y-5">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Webhook size={18} className="text-primary" />
          <h3 className="text-base font-semibold text-foreground">
            {t('webhooks.title', 'Webhooks')}
          </h3>
        </div>

        <Button
          size="sm"
          color="primary"
          variant="flat"
          startContent={<Plus size={14} />}
          onPress={() => setModalOpen(true)}
        >
          {t('webhooks.add', 'Add Webhook')}
        </Button>
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-8">
          <Spinner size="md" />
        </div>
      ) : webhooks.length === 0 ? (
        <p className="text-sm text-default-400 text-center py-6">
          {t('webhooks.empty', 'No webhooks configured')}
        </p>
      ) : (
        <div className="space-y-3">
          {webhooks.map((wh) => (
            <div
              key={wh.id}
              className="flex items-start gap-3 p-3 rounded-lg border border-default-200 bg-default-50"
            >
              <div className="flex-1 min-w-0 space-y-1">
                <p className="text-sm font-medium text-foreground truncate" title={wh.url}>
                  {wh.url}
                </p>
                <div className="flex flex-wrap items-center gap-1.5">
                  <Chip
                    size="sm"
                    variant="flat"
                    color={wh.is_active ? 'success' : 'default'}
                  >
                    {wh.is_active
                      ? t('webhooks.active', 'Active')
                      : t('webhooks.inactive', 'Inactive')}
                  </Chip>
                  {wh.failure_count > 0 && (
                    <Chip
                      size="sm"
                      variant="flat"
                      color="warning"
                      startContent={<AlertTriangle size={10} />}
                    >
                      {t('webhooks.failures', '{{count}} failures', {
                        count: wh.failure_count,
                      })}
                    </Chip>
                  )}
                  {wh.last_fired_at && (
                    <span className="text-xs text-default-400">
                      {t('webhooks.last_fired', 'Last fired')}{' '}
                      {formatDateValue(wh.last_fired_at)}
                    </span>
                  )}
                </div>
              </div>

              <div className="flex items-center gap-2 flex-shrink-0">
                <Switch
                  size="sm"
                  isSelected={wh.is_active}
                  onValueChange={(checked) => handleToggle(wh.id, checked)}
                  aria-label={t('webhooks.toggle_label', 'Toggle webhook')}
                />
                <Button
                  size="sm"
                  variant="flat"
                  color="danger"
                  isIconOnly
                  onPress={() => handleDelete(wh.id)}
                  aria-label={t('webhooks.delete_label', 'Delete webhook')}
                >
                  <Trash2 size={14} />
                </Button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Add Webhook Modal */}
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} size="lg">
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Webhook size={20} className="text-primary" />
            {t('webhooks.add_title', 'Add Webhook')}
          </ModalHeader>

          <ModalBody>
            <div className="space-y-4">
              <Input
                label={t('webhooks.url_label', 'Webhook URL')}
                placeholder="https://example.com/webhook"
                value={newUrl}
                onValueChange={setNewUrl}
                variant="bordered"
                type="url"
                isRequired
              />

              <div className="space-y-2">
                <p className="text-sm font-medium text-default-700">
                  {t('webhooks.events_label', 'Events')}
                </p>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  {AVAILABLE_EVENTS.map((event) => (
                    <Checkbox
                      key={event}
                      isSelected={newEvents.includes(event)}
                      onValueChange={() => toggleEvent(event)}
                      size="sm"
                    >
                      <span className="text-sm font-mono">{event}</span>
                    </Checkbox>
                  ))}
                </div>
              </div>

              <Input
                label={t('webhooks.secret_label', 'Secret (optional)')}
                placeholder={t('webhooks.secret_placeholder', 'Signing secret for payload verification')}
                value={newSecret}
                onValueChange={setNewSecret}
                variant="bordered"
                type="password"
              />
            </div>
          </ModalBody>

          <ModalFooter>
            <Button variant="flat" onPress={() => setModalOpen(false)}>
              {t('common:cancel', 'Cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleCreate}
              isLoading={creating}
            >
              {t('webhooks.create_btn', 'Create Webhook')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </GlassCard>
  );
}

export default WebhookConfigPanel;
