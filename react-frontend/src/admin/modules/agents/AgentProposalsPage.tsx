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
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Tab,
  Tabs,
  Textarea,
} from '@heroui/react';
import Bot from 'lucide-react/icons/bot';
import CheckCircle2 from 'lucide-react/icons/check-circle-2';
import XCircle from 'lucide-react/icons/x-circle';
import Edit3 from 'lucide-react/icons/edit-3';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { useAdminPageMeta } from '../../AdminMetaContext';
import { EmptyState } from '../../components/EmptyState';
import { PageHeader } from '../../components/PageHeader';

type ProposalStatus = 'pending' | 'approved' | 'rejected' | 'all';

interface AgentProposal {
  id: number;
  tenant_id: number;
  run_id: number;
  agent_definition_id: number | null;
  proposal_type: string;
  subject_user_id: number | null;
  target_user_id: number | null;
  proposal_data: Record<string, unknown>;
  reasoning: string | null;
  status: string;
  confidence_score: number | string | null;
  created_at: string;
}

export default function AgentProposalsPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('agents.proposals.meta.title'));
  useAdminPageMeta({
    title: t('agents.proposals.meta.title'),
    description: t('agents.proposals.meta.description'),
  });
  const toast = useToast();

  const [filter, setFilter] = useState<ProposalStatus>('pending');
  const [items, setItems] = useState<AgentProposal[]>([]);
  const [loading, setLoading] = useState(false);
  const [busyId, setBusyId] = useState<number | null>(null);

  const [rejecting, setRejecting] = useState<AgentProposal | null>(null);
  const [rejectNote, setRejectNote] = useState('');

  const [editing, setEditing] = useState<AgentProposal | null>(null);
  const [editPayload, setEditPayload] = useState('');

  const fetchItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<{ items: AgentProposal[] }>(
        `/v2/admin/agents/proposals?status=${filter}&per_page=100`
      );
      setItems(res.data?.items ?? []);
    } catch {
      toast.error(t('agents.proposals.toasts.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [filter, t, toast]);

  useEffect(() => {
    void fetchItems();
  }, [fetchItems]);

  const handleApprove = async (p: AgentProposal) => {
    setBusyId(p.id);
    try {
      await api.post(`/v2/admin/agents/proposals/${p.id}/approve`, {});
      toast.success(t('agents.proposals.toasts.approved'));
      await fetchItems();
    } catch {
      toast.error(t('agents.proposals.toasts.approve_failed'));
    } finally {
      setBusyId(null);
    }
  };

  const handleConfirmReject = async () => {
    if (!rejecting) return;
    setBusyId(rejecting.id);
    try {
      await api.post(`/v2/admin/agents/proposals/${rejecting.id}/reject`, { note: rejectNote });
      toast.success(t('agents.proposals.toasts.rejected'));
      setRejecting(null);
      setRejectNote('');
      await fetchItems();
    } catch {
      toast.error(t('agents.proposals.toasts.reject_failed'));
    } finally {
      setBusyId(null);
    }
  };

  const openEdit = (p: AgentProposal) => {
    setEditing(p);
    setEditPayload(JSON.stringify(p.proposal_data ?? {}, null, 2));
  };

  const handleEditApprove = async () => {
    if (!editing) return;
    let parsed: unknown;
    try {
      parsed = JSON.parse(editPayload);
    } catch {
      toast.error(t('agents.proposals.toasts.invalid_json'));
      return;
    }
    setBusyId(editing.id);
    try {
      await api.post(`/v2/admin/agents/proposals/${editing.id}/edit-approve`, {
        edited_payload: parsed,
      });
      toast.success(t('agents.proposals.toasts.edited_approved'));
      setEditing(null);
      await fetchItems();
    } catch {
      toast.error(t('agents.proposals.toasts.edit_approve_failed'));
    } finally {
      setBusyId(null);
    }
  };

  const confidenceColor = (score: number | string | null): 'success' | 'warning' | 'danger' | 'default' => {
    const n = typeof score === 'string' ? parseFloat(score) : score ?? 0;
    if (n >= 0.75) return 'success';
    if (n >= 0.5) return 'warning';
    if (n > 0) return 'danger';
    return 'default';
  };

  return (
    <div className="p-6 space-y-6">
      <PageHeader
        title={t('agents.proposals.title')}
        description={t('agents.proposals.subtitle')}
        icon={<Bot className="h-5 w-5" />}
      />

      <Tabs
        selectedKey={filter}
        onSelectionChange={(k) => setFilter(k as ProposalStatus)}
      >
        <Tab key="pending" title={t('agents.proposals.tabs.pending')} />
        <Tab key="approved" title={t('agents.proposals.tabs.approved')} />
        <Tab key="rejected" title={t('agents.proposals.tabs.rejected')} />
        <Tab key="all" title={t('agents.proposals.tabs.all')} />
      </Tabs>

      {loading && (
        <Card shadow="sm" className="border border-divider/70 bg-content1">
          <CardBody className="py-8 text-sm text-default-500">
            {t('agents.proposals.loading')}
          </CardBody>
        </Card>
      )}

      {!loading && items.length === 0 && (
        <EmptyState
          icon={Bot}
          title={t('agents.proposals.empty.title')}
          description={t('agents.proposals.empty.description')}
        />
      )}

      {!loading && (
      <div className="space-y-4">
        {items.map((p) => (
          <Card key={p.id} className="border border-default-200">
            <CardHeader className="flex flex-wrap gap-2 justify-between items-start">
              <div className="flex flex-wrap gap-2 items-center">
                <Chip size="sm" variant="flat" color="primary">{p.proposal_type}</Chip>
                <Chip size="sm" variant="flat" color={confidenceColor(p.confidence_score)}>
                  {t('agents.proposals.confidence', {
                    score: (typeof p.confidence_score === 'string'
                      ? parseFloat(p.confidence_score)
                      : (p.confidence_score ?? 0)).toFixed(2),
                  })}
                </Chip>
                <Chip size="sm" variant="flat">{t('agents.proposals.run_id', { id: p.run_id })}</Chip>
                {p.subject_user_id && (
                  <Chip size="sm" variant="flat">{t('agents.proposals.subject_user', { id: p.subject_user_id })}</Chip>
                )}
                {p.target_user_id && (
                  <Chip size="sm" variant="flat">{t('agents.proposals.target_user', { id: p.target_user_id })}</Chip>
                )}
                <Chip size="sm" variant="flat" color={
                  p.status === 'approved' ? 'success' :
                  p.status === 'rejected' ? 'danger' : 'warning'
                }>{t(`agents.proposal_status.${p.status}`, p.status)}</Chip>
              </div>
              <span className="text-xs text-default-400">
                {new Date(p.created_at).toLocaleString()}
              </span>
            </CardHeader>
            <CardBody className="space-y-3">
              {p.reasoning && (
                <div>
                  <p className="text-xs font-semibold uppercase text-default-500">{t('agents.proposals.labels.reasoning')}</p>
                  <p className="text-sm">{p.reasoning}</p>
                </div>
              )}
              <div>
                <p className="text-xs font-semibold uppercase text-default-500">{t('agents.proposals.labels.payload')}</p>
                <pre className="text-xs bg-default-100 p-3 rounded-md overflow-x-auto">
                  {JSON.stringify(p.proposal_data, null, 2)}
                </pre>
              </div>
              {p.status === 'pending_review' && (
                <div className="flex gap-2 flex-wrap">
                  <Button
                    color="success"
                    variant="flat"
                    size="sm"
                    startContent={<CheckCircle2 className="w-4 h-4" />}
                    isLoading={busyId === p.id}
                    onPress={() => handleApprove(p)}
                  >
                    {t('agents.proposals.actions.approve')}
                  </Button>
                  <Button
                    color="primary"
                    variant="flat"
                    size="sm"
                    startContent={<Edit3 className="w-4 h-4" />}
                    onPress={() => openEdit(p)}
                  >
                    {t('agents.proposals.actions.edit_approve')}
                  </Button>
                  <Button
                    color="danger"
                    variant="flat"
                    size="sm"
                    startContent={<XCircle className="w-4 h-4" />}
                    onPress={() => { setRejecting(p); setRejectNote(''); }}
                  >
                    {t('agents.proposals.actions.reject')}
                  </Button>
                </div>
              )}
            </CardBody>
          </Card>
        ))}
      </div>
      )}

      <Modal isOpen={!!rejecting} onClose={() => setRejecting(null)}>
        <ModalContent>
          <ModalHeader>{t('agents.proposals.reject_modal.title', { id: rejecting?.id ?? '' })}</ModalHeader>
          <ModalBody>
            <Textarea
              label={t('agents.proposals.reject_modal.note')}
              value={rejectNote}
              onValueChange={setRejectNote}
              minRows={3}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setRejecting(null)}>{t('agents.actions.cancel')}</Button>
            <Button color="danger" isLoading={busyId === rejecting?.id} onPress={handleConfirmReject}>
              {t('agents.proposals.actions.reject')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      <Modal isOpen={!!editing} onClose={() => setEditing(null)} size="2xl">
        <ModalContent>
          <ModalHeader>{t('agents.proposals.edit_modal.title', { id: editing?.id ?? '' })}</ModalHeader>
          <ModalBody>
            <Textarea
              label={t('agents.proposals.edit_modal.payload_json')}
              value={editPayload}
              onValueChange={setEditPayload}
              minRows={12}
              className="font-mono text-xs"
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => setEditing(null)}>{t('agents.actions.cancel')}</Button>
            <Button color="success" isLoading={busyId === editing?.id} onPress={handleEditApprove}>
              {t('agents.proposals.actions.save_approve')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
