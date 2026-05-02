// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FederationPeersAdminPage — AG23 follow-up
 *
 * Admin console for cross-platform federation peers. Each peer is a remote
 * NEXUS install this cooperative has agreed to send/receive hour transfers
 * with. The shared secret is shown ONCE on creation or rotation — copy it
 * to the remote side immediately.
 *
 * Admin English only — no t() calls.
 */

import { useCallback, useEffect, useState } from 'react';
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
  Select,
  SelectItem,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Textarea,
  Tooltip,
} from '@heroui/react';
import Copy from 'lucide-react/icons/copy';
import Info from 'lucide-react/icons/info';
import KeyRound from 'lucide-react/icons/key-round';
import Plus from 'lucide-react/icons/plus';
import Power from 'lucide-react/icons/power';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Server from 'lucide-react/icons/server';
import Trash2 from 'lucide-react/icons/trash-2';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { PageHeader } from '../../components';

type PeerStatus = 'pending' | 'active' | 'suspended';

interface Peer {
  id: number;
  peer_slug: string;
  display_name: string;
  base_url: string;
  shared_secret: string | null;
  shared_secret_set: boolean;
  status: PeerStatus;
  notes: string | null;
  last_handshake_at: string | null;
  created_at: string;
  updated_at: string;
}

const STATUS_COLOR: Record<PeerStatus, 'default' | 'success' | 'warning' | 'danger'> = {
  pending:   'warning',
  active:    'success',
  suspended: 'danger',
};

export default function FederationPeersAdminPage() {
  usePageTitle('Federation Peers — Admin');
  const { showToast } = useToast();

  const [peers, setPeers] = useState<Peer[]>([]);
  const [loading, setLoading] = useState(true);

  // Create modal
  const [createOpen, setCreateOpen] = useState(false);
  const [creating, setCreating] = useState(false);
  const [peerSlug, setPeerSlug] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [baseUrl, setBaseUrl] = useState('https://');
  const [notes, setNotes] = useState('');

  // Secret-reveal modal (after create / rotate)
  const [secretReveal, setSecretReveal] = useState<{ peerSlug: string; baseUrl: string; secret: string } | null>(null);

  // ── Fetch ────────────────────────────────────────────────────────────────

  const fetchPeers = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<{ peers: Peer[] }>('/v2/admin/caring-community/federation-peers');
      setPeers(res.data?.peers ?? []);
    } catch (err) {
      logError('FederationPeersAdminPage.fetch', err);
      showToast('Failed to load federation peers.', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    void fetchPeers();
  }, [fetchPeers]);

  // ── Create ───────────────────────────────────────────────────────────────

  const resetForm = () => {
    setPeerSlug('');
    setDisplayName('');
    setBaseUrl('https://');
    setNotes('');
  };

  async function handleCreate() {
    if (!peerSlug.trim() || !displayName.trim() || !baseUrl.trim()) {
      showToast('All required fields must be filled.', 'error');
      return;
    }
    setCreating(true);
    try {
      const res = await api.post<Peer>('/v2/admin/caring-community/federation-peers', {
        peer_slug: peerSlug.trim().toLowerCase(),
        display_name: displayName.trim(),
        base_url: baseUrl.trim().replace(/\/+$/, ''),
        notes: notes.trim() || null,
      });
      const peer = res.data;
      if (peer && peer.shared_secret) {
        setSecretReveal({
          peerSlug: peer.peer_slug,
          baseUrl: peer.base_url,
          secret: peer.shared_secret,
        });
      }
      showToast('Federation peer created.', 'success');
      setCreateOpen(false);
      resetForm();
      void fetchPeers();
    } catch (err) {
      logError('FederationPeersAdminPage.create', err);
      showToast('Failed to create peer.', 'error');
    } finally {
      setCreating(false);
    }
  }

  // ── Status / rotate / delete ─────────────────────────────────────────────

  async function handleStatus(peer: Peer, status: PeerStatus) {
    try {
      await api.put(`/v2/admin/caring-community/federation-peers/${peer.id}/status`, { status });
      showToast(`Peer status set to ${status}.`, 'success');
      void fetchPeers();
    } catch (err) {
      logError('FederationPeersAdminPage.status', err);
      showToast('Failed to update peer status.', 'error');
    }
  }

  async function handleRotate(peer: Peer) {
    if (!window.confirm(
      `Rotate the shared secret for "${peer.display_name}"? The old secret stops working immediately and you must update the remote side.`,
    )) {
      return;
    }
    try {
      const res = await api.post<Peer>(`/v2/admin/caring-community/federation-peers/${peer.id}/rotate-secret`, {});
      const updated = res.data;
      if (updated && updated.shared_secret) {
        setSecretReveal({
          peerSlug: updated.peer_slug,
          baseUrl: updated.base_url,
          secret: updated.shared_secret,
        });
      }
      showToast('Shared secret rotated.', 'success');
      void fetchPeers();
    } catch (err) {
      logError('FederationPeersAdminPage.rotate', err);
      showToast('Failed to rotate secret.', 'error');
    }
  }

  async function handleDelete(peer: Peer) {
    if (!window.confirm(`Delete federation peer "${peer.display_name}"? This is permanent.`)) {
      return;
    }
    try {
      await api.delete(`/v2/admin/caring-community/federation-peers/${peer.id}`);
      showToast('Peer deleted.', 'success');
      void fetchPeers();
    } catch (err) {
      logError('FederationPeersAdminPage.delete', err);
      showToast('Failed to delete peer.', 'error');
    }
  }

  function copyToClipboard(value: string) {
    void navigator.clipboard.writeText(value).then(
      () => showToast('Copied to clipboard.', 'success'),
      () => showToast('Failed to copy.', 'error'),
    );
  }

  // ── Render ───────────────────────────────────────────────────────────────

  return (
    <div className="space-y-5">
      <PageHeader
        title="Federation Peers"
        subtitle="Cross-platform partnerships for cooperative-to-cooperative hour transfers"
        icon={<Server size={20} />}
        actions={
          <div className="flex items-center gap-2">
            <Tooltip content="Refresh">
              <Button isIconOnly size="sm" variant="flat" onPress={() => void fetchPeers()} isLoading={loading}>
                <RefreshCw size={15} />
              </Button>
            </Tooltip>
            <Button
              size="sm"
              color="primary"
              startContent={<Plus size={15} />}
              onPress={() => { resetForm(); setCreateOpen(true); }}
            >
              Add Peer
            </Button>
          </div>
        }
      />

      {/* Intro card */}
      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                Federation Peers are other NEXUS cooperatives that your community has agreed to share
                members and hours with. Once a peer connection is established, members can transfer
                hours between communities and view federated listings. Each peer connection requires
                a shared secret that both sides must configure — contact the peer cooperative's
                administrator to exchange credentials.
              </p>
              <div className="space-y-0.5 pt-1 text-default-500">
                <p><strong>Shared secret:</strong> A cryptographic token used to authenticate peer-to-peer API calls. It must match exactly on both sides. Rotate it periodically (every 6–12 months) and always rotate immediately if you suspect it has been compromised.</p>
                <p><strong>Pending:</strong> Credentials set, awaiting first successful API call.</p>
                <p><strong>Active:</strong> Connected and syncing.</p>
                <p><strong>Suspended:</strong> Last sync failed or manually suspended — check that the peer's base URL is correct and reachable.</p>
              </div>
            </div>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardHeader className="flex items-center gap-2">
          <Server size={18} className="text-primary" />
          <span className="font-semibold">Registered Peers</span>
        </CardHeader>
        <CardBody className="p-0">
          {loading ? (
            <div className="flex justify-center py-10"><Spinner /></div>
          ) : (
            <Table aria-label="Federation peers" removeWrapper>
              <TableHeader>
                <TableColumn>Name</TableColumn>
                <TableColumn>Slug</TableColumn>
                <TableColumn>Base URL</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Last Handshake</TableColumn>
                <TableColumn>Actions</TableColumn>
              </TableHeader>
              <TableBody emptyContent="No federation peers yet — add one to start.">
                {peers.map((peer) => (
                  <TableRow key={peer.id}>
                    <TableCell>
                      <div className="font-medium">{peer.display_name}</div>
                      {peer.notes && (
                        <div className="text-xs text-default-500 max-w-xs truncate">{peer.notes}</div>
                      )}
                    </TableCell>
                    <TableCell>
                      <code className="text-xs bg-default-100 px-1.5 py-0.5 rounded">
                        {peer.peer_slug}
                      </code>
                    </TableCell>
                    <TableCell>
                      <a
                        href={peer.base_url}
                        target="_blank"
                        rel="noreferrer"
                        className="text-primary text-xs"
                      >
                        {peer.base_url}
                      </a>
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" color={STATUS_COLOR[peer.status]} variant="flat">
                        {peer.status}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <span className="text-xs text-default-500">
                        {peer.last_handshake_at
                          ? new Date(peer.last_handshake_at).toLocaleString()
                          : 'Never'}
                      </span>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1.5">
                        <Select
                          aria-label="Status"
                          size="sm"
                          className="min-w-[110px]"
                          selectedKeys={[peer.status]}
                          onChange={(e) => {
                            const v = e.target.value as PeerStatus;
                            if (v && v !== peer.status) void handleStatus(peer, v);
                          }}
                        >
                          {(['pending', 'active', 'suspended'] as PeerStatus[]).map((s) => (
                            <SelectItem key={s}>{s}</SelectItem>
                          ))}
                        </Select>
                        <Tooltip content="Rotate shared secret">
                          <Button
                            size="sm"
                            variant="flat"
                            isIconOnly
                            onPress={() => void handleRotate(peer)}
                            aria-label="Rotate secret"
                          >
                            <KeyRound size={14} />
                          </Button>
                        </Tooltip>
                        <Tooltip content="Delete peer">
                          <Button
                            size="sm"
                            variant="flat"
                            color="danger"
                            isIconOnly
                            onPress={() => void handleDelete(peer)}
                            aria-label="Delete peer"
                          >
                            <Trash2 size={14} />
                          </Button>
                        </Tooltip>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      {/* Create Modal */}
      <Modal isOpen={createOpen} onOpenChange={setCreateOpen} size="2xl">
        <ModalContent>
          {(close) => (
            <>
              <ModalHeader>Add Federation Peer</ModalHeader>
              <ModalBody className="gap-4">
                <Input
                  label="Peer Slug"
                  isRequired
                  description="Lowercase alphanumeric + hyphens. Used as destination cooperative slug for outbound transfers."
                  placeholder="kiss-bern"
                  value={peerSlug}
                  onValueChange={setPeerSlug}
                />
                <Input
                  label="Display Name"
                  isRequired
                  placeholder="KISS Bern Cooperative"
                  value={displayName}
                  onValueChange={setDisplayName}
                />
                <Input
                  label="Base URL"
                  isRequired
                  description="HTTPS URL of the remote NEXUS API. The /v2/federation/hour-transfer/inbound path is appended automatically."
                  placeholder="https://api.kiss-bern.ch"
                  value={baseUrl}
                  onValueChange={setBaseUrl}
                />
                <Textarea
                  label="Notes"
                  placeholder="Internal notes — partner contact, agreement reference..."
                  value={notes}
                  onValueChange={setNotes}
                  minRows={2}
                />
                <div className="text-xs text-default-500">
                  <Power size={12} className="inline mr-1" />
                  Status starts as <strong>pending</strong>. After the remote side has
                  registered the same shared secret, set both sides to <strong>active</strong>.
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={close} isDisabled={creating}>Cancel</Button>
                <Button color="primary" onPress={() => void handleCreate()} isLoading={creating}>
                  Create &amp; Reveal Secret
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Secret-reveal modal (one-time) */}
      <Modal
        isOpen={secretReveal !== null}
        onOpenChange={(open) => { if (!open) setSecretReveal(null); }}
        size="2xl"
      >
        <ModalContent>
          {(close) => (
            <>
              <ModalHeader>One-Time Shared Secret</ModalHeader>
              <ModalBody className="gap-3">
                <p className="text-sm">
                  This secret is shown <strong>only once</strong>. Copy it now and
                  paste it into the remote NEXUS install&apos;s federation peer entry
                  for this cooperative. If you lose it, you must rotate the secret
                  here and update the remote side again.
                </p>
                {secretReveal && (
                  <>
                    <div className="rounded-lg border border-default-200 bg-default-100 p-3 font-mono text-xs break-all">
                      {secretReveal.secret}
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                      <Button
                        variant="flat"
                        startContent={<Copy size={14} />}
                        onPress={() => copyToClipboard(secretReveal.secret)}
                      >
                        Copy Secret
                      </Button>
                      <Button
                        variant="flat"
                        startContent={<Copy size={14} />}
                        onPress={() => copyToClipboard(secretReveal.peerSlug)}
                      >
                        Copy Peer Slug
                      </Button>
                    </div>
                    <div className="text-xs text-default-500">
                      <strong>Peer slug:</strong> <code>{secretReveal.peerSlug}</code><br />
                      <strong>Base URL:</strong> <code>{secretReveal.baseUrl}</code>
                    </div>
                  </>
                )}
              </ModalBody>
              <ModalFooter>
                <Button color="primary" onPress={close}>I have copied the secret</Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
