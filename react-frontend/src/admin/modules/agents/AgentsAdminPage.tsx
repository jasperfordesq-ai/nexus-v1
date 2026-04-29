// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG61 — Agent Definitions admin page.
 * ADMIN IS ENGLISH-ONLY — NO t() calls.
 */

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
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
  usePageTitle('AI Agents');
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
      toast.error('Failed to load agent definitions');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    void fetchItems();
  }, [fetchItems]);

  const handleToggle = async (def: AgentDefinition) => {
    setBusyId(def.id);
    try {
      await api.post(`/v2/admin/agents/${def.id}/toggle`, {});
      toast.success(def.is_enabled ? `${def.name} disabled` : `${def.name} enabled`);
      await fetchItems();
    } catch {
      toast.error('Failed to toggle agent');
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
        toast.error(`Run failed: ${data.error}`);
      } else {
        toast.success(`Run #${data?.run_id} created ${data?.proposals_created ?? 0} proposal(s)`);
      }
      await fetchItems();
    } catch {
      toast.error('Failed to run agent');
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
      toast.error('Config is not valid JSON');
      return;
    }
    try {
      await api.patch(`/v2/admin/agents/${editing.id}`, {
        name: editName,
        config: parsedConfig,
      });
      toast.success('Agent updated');
      setEditing(null);
      await fetchItems();
    } catch {
      toast.error('Failed to update agent');
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center gap-3">
        <Bot className="w-7 h-7 text-primary" />
        <div>
          <h1 className="text-2xl font-bold">AI Agents</h1>
          <p className="text-sm text-default-500">
            AG61 — autonomous agents that draft proposals for admin approval. All actions remain proposals
            until you approve them.
          </p>
        </div>
      </div>

      {loading && <p className="text-default-500">Loading…</p>}

      {!loading && items.length === 0 && (
        <Card>
          <CardBody className="text-default-500">
            No agent definitions yet. Run <code>php artisan tenant:seed-agents</code> to create the four
            default agents for this tenant.
          </CardBody>
        </Card>
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
                  {def.is_enabled ? 'Enabled' : 'Disabled'}
                </Chip>
                <Chip size="sm" variant="flat">{def.agent_type}</Chip>
                {def.last_run_at && (
                  <Chip size="sm" variant="flat" color="primary">
                    Last run: {new Date(def.last_run_at).toLocaleString()}
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
                  Run now
                </Button>
                <Button
                  size="sm"
                  variant="flat"
                  startContent={<Edit3 className="w-4 h-4" />}
                  onPress={() => openEdit(def)}
                >
                  Edit config
                </Button>
              </div>
            </CardBody>
          </Card>
        ))}
      </div>

      <Modal isOpen={!!editing} onClose={() => setEditing(null)} size="2xl">
        <ModalContent>
          <ModalHeader>Edit {editing?.name}</ModalHeader>
          <ModalBody className="space-y-3">
            <div>
              <label className="text-sm font-medium">Name</label>
              <input
                className="w-full mt-1 px-3 py-2 border border-default-200 rounded-md bg-background"
                value={editName}
                onChange={(e) => setEditName(e.target.value)}
              />
            </div>
            <div>
              <label className="text-sm font-medium">Config (JSON)</label>
              <Textarea
                value={editConfig}
                onValueChange={setEditConfig}
                minRows={10}
                className="font-mono text-xs"
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setEditing(null)}>Cancel</Button>
            <Button color="primary" onPress={handleSaveEdit}>Save</Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
