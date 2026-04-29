// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG60 — Admin: API Partners management.
 *
 * Tenant admins use this page to provision Partner API integrations
 * (banks, payment processors, municipal admin systems), suspend or
 * activate them, regenerate client credentials, and inspect the
 * recent call log per partner.
 *
 * English-only by design — see project CLAUDE.md "ADMIN PANEL IS ENGLISH-ONLY".
 *
 * Backed by `/api/v2/admin/api-partners/*` (ApiPartnerAdminController).
 */

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  Chip,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Spinner,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Tabs,
  Tab,
  Textarea,
} from '@heroui/react';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Pause from 'lucide-react/icons/pause';
import Play from 'lucide-react/icons/play';
import Eye from 'lucide-react/icons/eye';
import Copy from 'lucide-react/icons/copy';
import Key from 'lucide-react/icons/key';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

// ─── Types ────────────────────────────────────────────────────────────────

interface ApiPartner {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  contact_email: string | null;
  status: 'pending' | 'active' | 'suspended';
  is_sandbox: boolean;
  allowed_scopes: string[];
  allowed_ip_cidrs: string[];
  rate_limit_per_minute: number;
  created_at: string | null;
  updated_at: string | null;
}

interface IssuedCredentials {
  client_id: string;
  client_secret: string;
}

interface CallLogEntry {
  id: number;
  method: string;
  path: string;
  status_code: number;
  response_time_ms: number;
  ip: string | null;
  user_agent: string | null;
  created_at: string;
}

const ALL_SCOPES = [
  'users.read',
  'users.pii',
  'listings.read',
  'wallet.read',
  'wallet.write',
  'aggregates.read',
  'webhooks.manage',
];

// ─── Component ────────────────────────────────────────────────────────────

export default function ApiPartnersAdminPage() {
  usePageTitle('API Partners');
  const toast = useToast();

  const [partners, setPartners] = useState<ApiPartner[]>([]);
  const [loading, setLoading] = useState(true);
  const [createOpen, setCreateOpen] = useState(false);
  const [credsModal, setCredsModal] = useState<IssuedCredentials | null>(null);
  const [detailPartner, setDetailPartner] = useState<ApiPartner | null>(null);
  const [callLog, setCallLog] = useState<CallLogEntry[]>([]);
  const [callLogLoading, setCallLogLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<{ partners: ApiPartner[] }>('/v2/admin/api-partners');
      setPartners(res.data?.partners ?? []);
    } catch {
      toast.error('Failed to load partners');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleSuspend = async (id: number) => {
    try {
      await api.post(`/v2/admin/api-partners/${id}/suspend`);
      toast.success('Partner suspended');
      void load();
    } catch {
      toast.error('Suspend failed');
    }
  };

  const handleActivate = async (id: number) => {
    try {
      await api.post(`/v2/admin/api-partners/${id}/activate`);
      toast.success('Partner activated');
      void load();
    } catch {
      toast.error('Activate failed');
    }
  };

  const handleRegenerate = async (id: number) => {
    if (!window.confirm('Regenerate credentials? The old client_id/client_secret will be revoked immediately.')) return;
    try {
      const res = await api.post<{ credentials: IssuedCredentials }>(
        `/v2/admin/api-partners/${id}/regenerate-credentials`,
      );
      if (res.data?.credentials) setCredsModal(res.data.credentials);
    } catch {
      toast.error('Regenerate failed');
    }
  };

  const openDetail = async (partner: ApiPartner) => {
    setDetailPartner(partner);
    setCallLogLoading(true);
    try {
      const res = await api.get<{ items: CallLogEntry[] }>(
        `/v2/admin/api-partners/${partner.id}/call-log?per_page=50`,
      );
      setCallLog(res.data?.items ?? []);
    } catch {
      setCallLog([]);
    } finally {
      setCallLogLoading(false);
    }
  };

  const statusColor = (s: ApiPartner['status']): 'success' | 'warning' | 'danger' => {
    if (s === 'active') return 'success';
    if (s === 'suspended') return 'danger';
    return 'warning';
  };

  return (
    <div className="p-6">
      <PageHeader
        title="API Partners"
        description="Provision and manage external integrations (banks, payment processors, admin systems) that call the Partner API."
        actions={
          <Button color="primary" startContent={<Plus size={16} />} onPress={() => setCreateOpen(true)}>
            New partner
          </Button>
        }
      />

      <Card shadow="sm">
        <CardBody className="p-0">
          {loading ? (
            <div className="p-10 flex justify-center">
              <Spinner />
            </div>
          ) : partners.length === 0 ? (
            <div className="p-10 text-center text-[var(--color-text-muted)]">
              No partners yet. Create one to issue client credentials.
            </div>
          ) : (
            <Table aria-label="API partners" removeWrapper>
              <TableHeader>
                <TableColumn>Name</TableColumn>
                <TableColumn>Slug</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Sandbox</TableColumn>
                <TableColumn>Rate limit</TableColumn>
                <TableColumn>Scopes</TableColumn>
                <TableColumn>Actions</TableColumn>
              </TableHeader>
              <TableBody>
                {partners.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell className="font-medium">{p.name}</TableCell>
                    <TableCell className="font-mono text-xs">{p.slug}</TableCell>
                    <TableCell>
                      <Chip size="sm" color={statusColor(p.status)} variant="flat">
                        {p.status}
                      </Chip>
                    </TableCell>
                    <TableCell>{p.is_sandbox ? 'Yes' : 'No'}</TableCell>
                    <TableCell>{p.rate_limit_per_minute}/min</TableCell>
                    <TableCell className="text-xs">{p.allowed_scopes.join(', ') || '—'}</TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button
                          size="sm"
                          variant="light"
                          isIconOnly
                          aria-label="View call log"
                          onPress={() => openDetail(p)}
                        >
                          <Eye size={16} />
                        </Button>
                        <Button
                          size="sm"
                          variant="light"
                          isIconOnly
                          aria-label="Regenerate credentials"
                          onPress={() => handleRegenerate(p.id)}
                        >
                          <RefreshCw size={16} />
                        </Button>
                        {p.status === 'active' ? (
                          <Button
                            size="sm"
                            variant="light"
                            isIconOnly
                            aria-label="Suspend"
                            onPress={() => handleSuspend(p.id)}
                          >
                            <Pause size={16} />
                          </Button>
                        ) : (
                          <Button
                            size="sm"
                            variant="light"
                            isIconOnly
                            aria-label="Activate"
                            onPress={() => handleActivate(p.id)}
                          >
                            <Play size={16} />
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      <CreatePartnerModal
        isOpen={createOpen}
        onClose={() => setCreateOpen(false)}
        onCreated={(creds) => {
          setCreateOpen(false);
          setCredsModal(creds);
          void load();
        }}
      />

      <CredentialsModal credentials={credsModal} onClose={() => setCredsModal(null)} />

      <Modal isOpen={detailPartner !== null} onClose={() => setDetailPartner(null)} size="3xl">
        <ModalContent>
          <ModalHeader>
            {detailPartner?.name} <span className="text-sm text-[var(--color-text-muted)] ml-2">{detailPartner?.slug}</span>
          </ModalHeader>
          <ModalBody>
            <Tabs aria-label="Partner detail tabs">
              <Tab key="info" title="Info">
                <div className="text-sm space-y-2">
                  <div><strong>Status:</strong> {detailPartner?.status}</div>
                  <div><strong>Sandbox:</strong> {detailPartner?.is_sandbox ? 'Yes' : 'No'}</div>
                  <div><strong>Rate limit:</strong> {detailPartner?.rate_limit_per_minute}/min</div>
                  <div><strong>Scopes:</strong> {detailPartner?.allowed_scopes.join(', ') || '—'}</div>
                  <div><strong>IP allowlist:</strong> {detailPartner?.allowed_ip_cidrs.join(', ') || 'any'}</div>
                  <div><strong>Contact:</strong> {detailPartner?.contact_email || '—'}</div>
                </div>
              </Tab>
              <Tab key="log" title="Call log">
                {callLogLoading ? (
                  <div className="p-6 flex justify-center">
                    <Spinner />
                  </div>
                ) : callLog.length === 0 ? (
                  <div className="p-6 text-center text-[var(--color-text-muted)]">
                    No calls yet.
                  </div>
                ) : (
                  <Table aria-label="Recent calls" removeWrapper>
                    <TableHeader>
                      <TableColumn>When</TableColumn>
                      <TableColumn>Method</TableColumn>
                      <TableColumn>Path</TableColumn>
                      <TableColumn>Status</TableColumn>
                      <TableColumn>Duration</TableColumn>
                    </TableHeader>
                    <TableBody>
                      {callLog.map((c) => (
                        <TableRow key={c.id}>
                          <TableCell className="text-xs">{c.created_at}</TableCell>
                          <TableCell>
                            <Chip size="sm" variant="flat">{c.method}</Chip>
                          </TableCell>
                          <TableCell className="font-mono text-xs">{c.path}</TableCell>
                          <TableCell>{c.status_code}</TableCell>
                          <TableCell>{c.response_time_ms} ms</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </Tab>
            </Tabs>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={() => setDetailPartner(null)}>
              Close
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

// ─── Create modal ─────────────────────────────────────────────────────────

function CreatePartnerModal({
  isOpen,
  onClose,
  onCreated,
}: {
  isOpen: boolean;
  onClose: () => void;
  onCreated: (creds: IssuedCredentials) => void;
}) {
  const toast = useToast();
  const [name, setName] = useState('');
  const [contactEmail, setContactEmail] = useState('');
  const [description, setDescription] = useState('');
  const [scopes, setScopes] = useState<string[]>(['users.read']);
  const [rateLimit, setRateLimit] = useState('60');
  const [sandbox, setSandbox] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const reset = useCallback(() => {
    setName('');
    setContactEmail('');
    setDescription('');
    setScopes(['users.read']);
    setRateLimit('60');
    setSandbox(true);
  }, []);

  const handleSubmit = async () => {
    if (!name.trim()) {
      toast.error('Name is required');
      return;
    }
    setSubmitting(true);
    try {
      const res = await api.post<{ partner_id: number; credentials: IssuedCredentials }>(
        '/v2/admin/api-partners',
        {
          name: name.trim(),
          contact_email: contactEmail.trim() || null,
          description: description.trim() || null,
          allowed_scopes: scopes,
          rate_limit_per_minute: Number(rateLimit) || 60,
          is_sandbox: sandbox,
        },
      );
      if (!res.data?.credentials) {
        toast.error('Create failed');
        return;
      }
      reset();
      onCreated(res.data.credentials);
    } catch {
      toast.error('Create failed');
    } finally {
      setSubmitting(false);
    }
  };

  const toggleScope = (scope: string) => {
    setScopes((prev) =>
      prev.includes(scope) ? prev.filter((s) => s !== scope) : [...prev, scope],
    );
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="2xl">
      <ModalContent>
        <ModalHeader>New API partner</ModalHeader>
        <ModalBody>
          <div className="space-y-4">
            <Input
              label="Name"
              value={name}
              onValueChange={setName}
              placeholder="e.g. PostFinance Switzerland"
              isRequired
            />
            <Input
              label="Contact email"
              value={contactEmail}
              onValueChange={setContactEmail}
              placeholder="integrations@partner.example"
              type="email"
            />
            <Textarea
              label="Description"
              value={description}
              onValueChange={setDescription}
              minRows={2}
            />
            <div>
              <label className="text-sm font-medium block mb-2">Allowed scopes</label>
              <div className="flex flex-wrap gap-2">
                {ALL_SCOPES.map((s) => (
                  <Chip
                    key={s}
                    variant={scopes.includes(s) ? 'solid' : 'flat'}
                    color={scopes.includes(s) ? 'primary' : 'default'}
                    onClick={() => toggleScope(s)}
                    className="cursor-pointer"
                  >
                    {s}
                  </Chip>
                ))}
              </div>
            </div>
            <Input
              label="Rate limit (requests / minute)"
              value={rateLimit}
              onValueChange={setRateLimit}
              type="number"
              min={1}
              max={6000}
            />
            <Switch isSelected={sandbox} onValueChange={setSandbox}>
              Sandbox mode (read-only — writes will be rejected)
            </Switch>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button variant="light" onPress={onClose} isDisabled={submitting}>
            Cancel
          </Button>
          <Button color="primary" onPress={handleSubmit} isLoading={submitting}>
            Create &amp; issue credentials
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

// ─── Credentials reveal modal ─────────────────────────────────────────────

function CredentialsModal({
  credentials,
  onClose,
}: {
  credentials: IssuedCredentials | null;
  onClose: () => void;
}) {
  const toast = useToast();

  const copy = async (text: string, label: string) => {
    try {
      await navigator.clipboard.writeText(text);
      toast.success(`${label} copied`);
    } catch {
      toast.error('Copy failed — copy manually');
    }
  };

  return (
    <Modal isOpen={credentials !== null} onClose={onClose} size="2xl" isDismissable={false}>
      <ModalContent>
        <ModalHeader className="flex items-center gap-2">
          <Key size={18} /> Client credentials issued
        </ModalHeader>
        <ModalBody>
          <div className="bg-warning-50 dark:bg-warning-100/10 border border-warning-200 rounded p-3 text-sm mb-4">
            <strong>Save these now.</strong> The client secret will not be shown again. Hand them to your partner via a secure channel.
          </div>
          <div className="space-y-3">
            <div>
              <label className="text-xs text-[var(--color-text-muted)] block mb-1">client_id</label>
              <div className="flex gap-2">
                <Input value={credentials?.client_id ?? ''} readOnly className="font-mono" />
                <Button
                  isIconOnly
                  variant="flat"
                  aria-label="Copy client_id"
                  onPress={() => credentials && copy(credentials.client_id, 'client_id')}
                >
                  <Copy size={16} />
                </Button>
              </div>
            </div>
            <div>
              <label className="text-xs text-[var(--color-text-muted)] block mb-1">client_secret</label>
              <div className="flex gap-2">
                <Input value={credentials?.client_secret ?? ''} readOnly className="font-mono" />
                <Button
                  isIconOnly
                  variant="flat"
                  aria-label="Copy client_secret"
                  onPress={() => credentials && copy(credentials.client_secret, 'client_secret')}
                >
                  <Copy size={16} />
                </Button>
              </div>
            </div>
          </div>
        </ModalBody>
        <ModalFooter>
          <Button color="primary" onPress={onClose}>
            I have saved these
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
