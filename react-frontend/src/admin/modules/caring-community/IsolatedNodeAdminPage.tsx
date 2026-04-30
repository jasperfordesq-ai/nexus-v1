// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Progress,
  Select,
  SelectItem,
  Spinner,
  Textarea,
  Tooltip,
} from '@heroui/react';
import CheckCircle2 from 'lucide-react/icons/check-circle-2';
import Pencil from 'lucide-react/icons/pencil';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Server from 'lucide-react/icons/server';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

type ItemStatus = 'pending' | 'in_progress' | 'decided' | 'blocked';
type ItemType = 'enum' | 'text' | 'url' | 'choice';

interface DecisionItem {
  key: string;
  label: string;
  type: ItemType;
  choices: string[] | null;
  help: string;
  value: string | null;
  owner: string | null;
  status: ItemStatus;
  notes: string | null;
  updated_at: string | null;
}

interface GateStatus {
  closed: boolean;
  decided_count: number;
  total_count: number;
  blockers: string[];
  status_counts: Record<ItemStatus, number>;
}

interface GateResponse {
  items: DecisionItem[];
  gate: GateStatus;
  last_updated_at: string | null;
}

interface UpdateResponse {
  item: DecisionItem;
  gate: GateStatus;
}

const STATUS_OPTIONS: { key: ItemStatus; label: string }[] = [
  { key: 'pending', label: 'Pending' },
  { key: 'in_progress', label: 'In progress' },
  { key: 'decided', label: 'Decided' },
  { key: 'blocked', label: 'Blocked' },
];

const STATUS_COLORS: Record<ItemStatus, 'default' | 'warning' | 'success' | 'danger'> = {
  pending: 'default',
  in_progress: 'warning',
  decided: 'success',
  blocked: 'danger',
};

const STATUS_LABELS: Record<ItemStatus, string> = {
  pending: 'Pending',
  in_progress: 'In progress',
  decided: 'Decided',
  blocked: 'Blocked',
};

interface DraftState {
  value: string;
  owner: string;
  status: ItemStatus;
  notes: string;
}

function buildDraft(item: DecisionItem): DraftState {
  return {
    value: item.value ?? '',
    owner: item.owner ?? '',
    status: item.status ?? 'pending',
    notes: item.notes ?? '',
  };
}

export default function IsolatedNodeAdminPage() {
  usePageTitle('Isolated-Node Decision Gate');
  const { showToast } = useToast();

  const [data, setData] = useState<GateResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [editingKey, setEditingKey] = useState<string | null>(null);
  const [draft, setDraft] = useState<DraftState | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<GateResponse>('/v2/admin/caring-community/isolated-node');
      setData(res.data ?? null);
    } catch {
      showToast('Failed to load decision-gate data', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  const editingItem = useMemo<DecisionItem | null>(() => {
    if (!data || !editingKey) return null;
    return data.items.find((it) => it.key === editingKey) ?? null;
  }, [data, editingKey]);

  const closeModal = () => {
    setEditingKey(null);
    setDraft(null);
  };

  const openEditor = (item: DecisionItem) => {
    setEditingKey(item.key);
    setDraft(buildDraft(item));
  };

  const save = async () => {
    if (!editingKey || !draft || !editingItem) return;
    setSaving(true);
    try {
      const payload: Record<string, string | null> = {
        value: draft.value === '' ? null : draft.value,
        owner: draft.owner === '' ? null : draft.owner,
        status: draft.status,
        notes: draft.notes === '' ? null : draft.notes,
      };
      const res = await api.put<UpdateResponse>(
        `/v2/admin/caring-community/isolated-node/items/${encodeURIComponent(editingKey)}`,
        payload,
      );
      const updatedItem = res.data?.item;
      const updatedGate = res.data?.gate;
      if (updatedItem && updatedGate && data) {
        setData({
          ...data,
          items: data.items.map((it) => (it.key === updatedItem.key ? updatedItem : it)),
          gate: updatedGate,
          last_updated_at: updatedItem.updated_at ?? data.last_updated_at,
        });
      }
      showToast('Decision item updated', 'success');
      closeModal();
    } catch (err) {
      const msg = (err as { message?: string })?.message ?? 'Failed to save decision item';
      showToast(msg, 'error');
    } finally {
      setSaving(false);
    }
  };

  const renderValueChip = (item: DecisionItem) => {
    if (!item.value) {
      return <span className="text-default-400 text-sm italic">—</span>;
    }
    if (item.type === 'enum' || item.type === 'choice') {
      return (
        <Chip size="sm" variant="flat" color="primary">
          {item.value}
        </Chip>
      );
    }
    if (item.type === 'url') {
      return (
        <a
          href={item.value}
          target="_blank"
          rel="noopener noreferrer"
          className="text-primary text-sm underline break-all"
        >
          {item.value}
        </a>
      );
    }
    return <span className="text-sm break-words">{item.value}</span>;
  };

  const renderValueInput = () => {
    if (!editingItem || !draft) return null;
    if (editingItem.type === 'enum' || editingItem.type === 'choice') {
      return (
        <Select
          label="Value"
          description={editingItem.help}
          selectedKeys={draft.value ? [draft.value] : []}
          onSelectionChange={(keys) => {
            const next = Array.from(keys)[0];
            if (typeof next === 'string') {
              setDraft({ ...draft, value: next });
            }
          }}
        >
          {(editingItem.choices ?? []).map((opt) => (
            <SelectItem key={opt}>{opt}</SelectItem>
          ))}
        </Select>
      );
    }

    if (editingItem.type === 'url') {
      return (
        <Input
          label="Value"
          description={editingItem.help}
          type="url"
          placeholder="https://..."
          value={draft.value}
          onValueChange={(v) => setDraft({ ...draft, value: v })}
        />
      );
    }

    return (
      <Input
        label="Value"
        description={editingItem.help}
        value={draft.value}
        onValueChange={(v) => setDraft({ ...draft, value: v })}
      />
    );
  };

  const gate = data?.gate;
  const progressValue = gate && gate.total_count > 0 ? (gate.decided_count / gate.total_count) * 100 : 0;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Isolated-Node Decision Gate"
        subtitle="AG85 — readiness checklist for canton-controlled deployment"
        icon={<Server size={20} />}
        actions={
          <Tooltip content="Refresh">
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              onPress={load}
              isLoading={loading}
              aria-label="Refresh"
            >
              <RefreshCw size={15} />
            </Button>
          </Tooltip>
        }
      />

      {loading && (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      )}

      {!loading && data && gate && (
        <>
          <Card
            className={
              gate.closed
                ? 'border border-success-300 bg-success-50/50 dark:bg-success-900/10'
                : 'border border-warning-300 bg-warning-50/50 dark:bg-warning-900/10'
            }
          >
            <CardBody className="space-y-3 py-4">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  {gate.closed ? (
                    <CheckCircle2 size={22} className="text-success" />
                  ) : (
                    <ShieldAlert size={22} className="text-warning" />
                  )}
                  <div>
                    <p className="text-base font-semibold">
                      {gate.closed
                        ? 'Gate closed — ready to launch'
                        : 'Gate open — decisions still required'}
                    </p>
                    <p className="text-sm text-default-500">
                      {gate.decided_count} of {gate.total_count} items decided
                      {gate.blockers.length > 0
                        ? ` · ${gate.blockers.length} blocked`
                        : ''}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <Chip size="sm" variant="flat" color={STATUS_COLORS.pending}>
                    Pending: {gate.status_counts.pending}
                  </Chip>
                  <Chip size="sm" variant="flat" color={STATUS_COLORS.in_progress}>
                    In progress: {gate.status_counts.in_progress}
                  </Chip>
                  <Chip size="sm" variant="flat" color={STATUS_COLORS.decided}>
                    Decided: {gate.status_counts.decided}
                  </Chip>
                  <Chip size="sm" variant="flat" color={STATUS_COLORS.blocked}>
                    Blocked: {gate.status_counts.blocked}
                  </Chip>
                </div>
              </div>
              <Progress
                aria-label="Decision-gate progress"
                value={progressValue}
                color={gate.closed ? 'success' : 'warning'}
                className="max-w-full"
              />
            </CardBody>
          </Card>

          <div className="grid grid-cols-1 gap-4">
            {data.items.map((item) => (
              <Card key={item.key} className="border border-[var(--color-border)]">
                <CardHeader className="flex flex-wrap items-start justify-between gap-3 pb-2">
                  <div className="min-w-0 flex-1">
                    <p className="font-semibold text-sm">{item.label}</p>
                    <p className="text-xs text-default-500 mt-0.5">{item.help}</p>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    <Chip size="sm" variant="flat" color={STATUS_COLORS[item.status]}>
                      {STATUS_LABELS[item.status]}
                    </Chip>
                    <Button
                      size="sm"
                      variant="flat"
                      startContent={<Pencil size={13} />}
                      onPress={() => openEditor(item)}
                    >
                      Edit
                    </Button>
                  </div>
                </CardHeader>
                <CardBody className="pt-0">
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                    <div>
                      <p className="text-xs uppercase tracking-wide text-default-400">Value</p>
                      <div className="mt-1">{renderValueChip(item)}</div>
                    </div>
                    <div>
                      <p className="text-xs uppercase tracking-wide text-default-400">Owner</p>
                      <p className="mt-1">
                        {item.owner ? (
                          item.owner
                        ) : (
                          <span className="text-default-400 italic">unassigned</span>
                        )}
                      </p>
                    </div>
                    <div>
                      <p className="text-xs uppercase tracking-wide text-default-400">Notes</p>
                      <p className="mt-1 whitespace-pre-wrap break-words">
                        {item.notes ? (
                          item.notes
                        ) : (
                          <span className="text-default-400 italic">none</span>
                        )}
                      </p>
                    </div>
                  </div>
                </CardBody>
              </Card>
            ))}
          </div>

          {data.last_updated_at && (
            <>
              <Divider />
              <p className="text-xs text-default-500">
                Last updated {new Date(data.last_updated_at).toLocaleString()}
              </p>
            </>
          )}
        </>
      )}

      <Modal isOpen={!!editingItem} onClose={closeModal} size="lg" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader className="flex flex-col gap-1">
            <span>{editingItem?.label ?? 'Edit decision item'}</span>
            <span className="text-xs font-normal text-default-500">
              Update value, owner, status, and notes for this gate item
            </span>
          </ModalHeader>
          <ModalBody>
            {editingItem && draft && (
              <div className="space-y-4">
                {renderValueInput()}
                <Input
                  label="Owner"
                  description="Person or organisation responsible for this decision"
                  value={draft.owner}
                  onValueChange={(v) => setDraft({ ...draft, owner: v })}
                />
                <Select
                  label="Status"
                  selectedKeys={[draft.status]}
                  onSelectionChange={(keys) => {
                    const next = Array.from(keys)[0];
                    if (typeof next === 'string') {
                      setDraft({ ...draft, status: next as ItemStatus });
                    }
                  }}
                >
                  {STATUS_OPTIONS.map((opt) => (
                    <SelectItem key={opt.key}>{opt.label}</SelectItem>
                  ))}
                </Select>
                <Textarea
                  label="Notes"
                  description="Context, links, contract references, or blockers"
                  minRows={3}
                  value={draft.notes}
                  onValueChange={(v) => setDraft({ ...draft, notes: v })}
                />
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={closeModal} isDisabled={saving}>
              Cancel
            </Button>
            <Button color="primary" onPress={save} isLoading={saving}>
              Save changes
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
