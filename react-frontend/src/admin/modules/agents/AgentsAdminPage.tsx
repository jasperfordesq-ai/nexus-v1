// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Switch,
  Textarea,
} from '@heroui/react';
import Bot from 'lucide-react/icons/bot';
import Brain from 'lucide-react/icons/brain';
import Edit3 from 'lucide-react/icons/edit-3';
import Play from 'lucide-react/icons/play';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { useAdminPageMeta } from '../../AdminMetaContext';
import { EmptyState } from '../../components/EmptyState';
import { PageHeader } from '../../components/PageHeader';

interface AgentDefinition {
  id: number;
  tenant_id: number;
  slug: string;
  name: string;
  description: string | null;
  agent_type: string;
  config: Record<string, unknown> | null;
  is_enabled: boolean;
  last_run_at: string | null;
}

export default function AgentsAdminPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('agents.definitions.meta.title'));
  useAdminPageMeta({
    title: t('agents.definitions.meta.title'),
    description: t('agents.definitions.meta.description'),
  });
  const toast = useToast();

  const [items, setItems] = useState<AgentDefinition[]>([]);
  const [loading, setLoading] = useState(false);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [editing, setEditing] = useState<AgentDefinition | null>(null);
  const [editConfig, setEditConfig] = useState<string>('');
  const [editName, setEditName] = useState('');

  const fetchItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<{ items: AgentDefinition[] }>('/v2/admin/agents');
      setItems(res.data?.items ?? []);
    } catch {
      toast.error(t('agents.definitions.toasts.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [t, toast]);

  useEffect(() => {
    void fetchItems();
  }, [fetchItems]);

  const handleToggle = async (def: AgentDefinition) => {
    setBusyId(def.id);
    try {
      await api.post(`/v2/admin/agents/${def.id}/toggle`, {});
      toast.success(t(def.is_enabled ? 'agents.definitions.toasts.disabled' : 'agents.definitions.toasts.enabled', {
        name: def.name,
      }));
      await fetchItems();
    } catch {
      toast.error(t('agents.definitions.toasts.toggle_failed'));
    } finally {
      setBusyId(null);
    }
  };

  const handleRunNow = async (def: AgentDefinition) => {
    setBusyId(def.id);
    try {
      const res = await api.post<{ run_id?: number; proposals_created?: number; error?: string }>(
        `/v2/admin/agents/${def.id}/run-now`,
        {}
      );
      const data = res.data;
      if (data?.error) {
        toast.error(t('agents.definitions.toasts.run_failed_with_error', { error: data.error }));
      } else {
        toast.success(t('agents.definitions.toasts.run_created', {
          runId: data?.run_id ?? t('agents.common.empty_dash'),
          count: data?.proposals_created ?? 0,
        }));
      }
      await fetchItems();
    } catch {
      toast.error(t('agents.definitions.toasts.run_failed'));
    } finally {
      setBusyId(null);
    }
  };

  const openEdit = (def: AgentDefinition) => {
    setEditing(def);
    setEditName(def.name);
    setEditConfig(JSON.stringify(def.config ?? {}, null, 2));
  };

  const handleSaveEdit = async () => {
    if (!editing) return;
    let parsedConfig: unknown;
    try {
      parsedConfig = JSON.parse(editConfig);
    } catch {
      toast.error(t('agents.definitions.toasts.invalid_json'));
      return;
    }
    try {
      await api.patch(`/v2/admin/agents/${editing.id}`, {
        name: editName,
        config: parsedConfig,
      });
      toast.success(t('agents.definitions.toasts.updated'));
      setEditing(null);
      await fetchItems();
    } catch {
      toast.error(t('agents.definitions.toasts.update_failed'));
    }
  };

  return (
    <div className="p-6 space-y-6">
      <PageHeader
        title={t('agents.definitions.title')}
        description={t('agents.definitions.subtitle')}
        icon={<Bot className="h-5 w-5" />}
      />

      {loading && (
        <Card shadow="sm" className="border border-divider/70 bg-content1">
          <CardBody className="py-8 text-sm text-default-500">
            {t('agents.definitions.loading')}
          </CardBody>
        </Card>
      )}

      {!loading && items.length === 0 && (
        <EmptyState
          icon={Bot}
          title={t('agents.definitions.empty.title')}
          description={t('agents.definitions.empty.description')}
        />
      )}

      <div className="grid gap-4 md:grid-cols-2">
        {items.map((def) => (
          <Card key={def.id} className="border border-default-200">
            <CardHeader className="flex justify-between items-start gap-4">
              <div className="flex gap-3 items-start">
                <Brain className="w-6 h-6 text-primary mt-1" />
                <div>
                  <h2 className="text-lg font-semibold">{def.name}</h2>
                  <p className="text-xs text-default-500 font-mono">{def.slug}</p>
                </div>
              </div>
              <Switch
                isSelected={def.is_enabled}
                onValueChange={() => handleToggle(def)}
                isDisabled={busyId === def.id}
              />
            </CardHeader>
            <CardBody className="space-y-3">
              {def.description && (
                <p className="text-sm text-default-600">{def.description}</p>
              )}
              <div className="flex flex-wrap gap-2 text-xs">
                <Chip size="sm" variant="flat" color={def.is_enabled ? 'success' : 'default'}>
                  {t(def.is_enabled ? 'agents.status.enabled' : 'agents.status.disabled')}
                </Chip>
                <Chip size="sm" variant="flat">{def.agent_type}</Chip>
                {def.last_run_at && (
                  <Chip size="sm" variant="flat" color="primary">
                    {t('agents.definitions.last_run', {
                      date: new Date(def.last_run_at).toLocaleString(),
                    })}
                  </Chip>
                )}
              </div>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  variant="flat"
                  startContent={<Play className="w-4 h-4" />}
                  onPress={() => handleRunNow(def)}
                  isDisabled={!def.is_enabled || busyId === def.id}
                  isLoading={busyId === def.id}
                >
                  {t('agents.definitions.actions.run_now')}
                </Button>
                <Button
                  size="sm"
                  variant="flat"
                  startContent={<Edit3 className="w-4 h-4" />}
                  onPress={() => openEdit(def)}
                >
                  {t('agents.definitions.actions.edit_config')}
                </Button>
              </div>
            </CardBody>
          </Card>
        ))}
      </div>

      <Modal isOpen={!!editing} onClose={() => setEditing(null)} size="2xl">
        <ModalContent>
          <ModalHeader>{t('agents.definitions.edit_modal.title', { name: editing?.name ?? '' })}</ModalHeader>
          <ModalBody className="space-y-3">
            <Input
              label={t('agents.definitions.edit_modal.name')}
              value={editName}
              onValueChange={setEditName}
            />
            <Textarea
              label={t('agents.definitions.edit_modal.config_json')}
              value={editConfig}
              onValueChange={setEditConfig}
              minRows={10}
              className="font-mono text-xs"
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setEditing(null)}>{t('agents.actions.cancel')}</Button>
            <Button color="primary" onPress={handleSaveEdit}>{t('agents.actions.save')}</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
