// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MunicipalVerificationAdminPage — AG29
 *
 * Manage municipal partnership verification status.
 *
 * - View current verification status (verified / pending / unverified)
 * - Request a DNS TXT verification token for a domain
 * - Apply a manual admin attestation
 * - Revoke an existing verification
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
  Code,
  Divider,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Spinner,
  Tabs,
  Tab,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import ShieldCheck from 'lucide-react/icons/shield-check';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Globe from 'lucide-react/icons/globe';
import Stamp from 'lucide-react/icons/stamp';
import Trash2 from 'lucide-react/icons/trash-2';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { PageHeader } from '../../components';

interface VerificationItem {
  id: number;
  domain: string;
  method: 'dns_txt' | 'admin_attestation' | string;
  status: 'verified' | 'pending' | 'revoked' | string;
  dns_record_name: string | null;
  dns_record_value: string | null;
  verified_at: string | null;
  revoked_at: string | null;
  attestation_note: string | null;
  created_at: string;
  updated_at: string;
}

interface VerificationResponse {
  verified: boolean;
  active: VerificationItem | null;
  items: VerificationItem[];
}

export default function MunicipalVerificationAdminPage() {
  const toast = useToast();
  usePageTitle('Municipal verification');

  const [data, setData] = useState<VerificationResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  // DNS form
  const [dnsDomain, setDnsDomain] = useState('');
  const [submittingDns, setSubmittingDns] = useState(false);

  // Attestation form
  const [attestDomain, setAttestDomain] = useState('');
  const [attestNote, setAttestNote] = useState('');
  const [submittingAttest, setSubmittingAttest] = useState(false);

  // Revoke modal
  const { isOpen: revokeOpen, onOpen: openRevoke, onClose: closeRevoke } = useDisclosure();
  const [revokeTarget, setRevokeTarget] = useState<VerificationItem | null>(null);
  const [submittingRevoke, setSubmittingRevoke] = useState(false);

  const load = useCallback(async () => {
    setRefreshing(true);
    try {
      const res = await api.get<VerificationResponse>('/v2/admin/reports/municipal-impact/verification');
      if (res.success && res.data) {
        setData(res.data);
      } else {
        toast.error(res.error || 'Failed to load verification status');
      }
    } catch (err) {
      logError('MunicipalVerificationAdminPage: load failed', err);
      toast.error('Failed to load verification status');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [toast]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleStartDns = useCallback(async () => {
    if (!dnsDomain.trim()) {
      toast.error('Enter a domain');
      return;
    }
    setSubmittingDns(true);
    try {
      const res = await api.post<{ verification: VerificationItem }>(
        '/v2/admin/reports/municipal-impact/verification/dns',
        { domain: dnsDomain.trim() },
      );
      if (res.success) {
        toast.success('DNS verification token generated');
        setDnsDomain('');
        void load();
      } else {
        toast.error(res.error || 'Failed to start DNS verification');
      }
    } catch (err) {
      logError('MunicipalVerificationAdminPage: start DNS failed', err);
      toast.error('Failed to start DNS verification');
    } finally {
      setSubmittingDns(false);
    }
  }, [dnsDomain, toast, load]);

  const handleAttest = useCallback(async () => {
    if (!attestDomain.trim()) {
      toast.error('Enter a domain');
      return;
    }
    setSubmittingAttest(true);
    try {
      const res = await api.post('/v2/admin/reports/municipal-impact/verification/attest', {
        domain: attestDomain.trim(),
        attestation_note: attestNote.trim(),
      });
      if (res.success) {
        toast.success('Verification attested');
        setAttestDomain('');
        setAttestNote('');
        void load();
      } else {
        toast.error(res.error || 'Failed to attest verification');
      }
    } catch (err) {
      logError('MunicipalVerificationAdminPage: attest failed', err);
      toast.error('Failed to attest verification');
    } finally {
      setSubmittingAttest(false);
    }
  }, [attestDomain, attestNote, toast, load]);

  const handleRevoke = useCallback(async () => {
    if (!revokeTarget) return;
    setSubmittingRevoke(true);
    try {
      const res = await api.post(
        `/v2/admin/reports/municipal-impact/verification/${revokeTarget.id}/revoke`,
        {},
      );
      if (res.success) {
        toast.success('Verification revoked');
        closeRevoke();
        setRevokeTarget(null);
        void load();
      } else {
        toast.error(res.error || 'Failed to revoke verification');
      }
    } catch (err) {
      logError('MunicipalVerificationAdminPage: revoke failed', err);
      toast.error('Failed to revoke verification');
    } finally {
      setSubmittingRevoke(false);
    }
  }, [revokeTarget, toast, load, closeRevoke]);

  if (loading || !data) {
    return (
      <div className="flex items-center justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  const items = data.items ?? [];
  const active = data.active;

  const StatusChip = ({ status }: { status: string }) => {
    if (status === 'verified') {
      return (
        <Chip color="success" variant="flat" size="sm" startContent={<ShieldCheck className="w-3.5 h-3.5" />}>
          Verified
        </Chip>
      );
    }
    if (status === 'pending') {
      return (
        <Chip color="warning" variant="flat" size="sm">
          Pending DNS
        </Chip>
      );
    }
    return (
      <Chip color="default" variant="flat" size="sm" startContent={<ShieldAlert className="w-3.5 h-3.5" />}>
        {status}
      </Chip>
    );
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Municipal verification"
        description="Mark this community as a verified municipal partner. Adds a 'Verified municipality' badge to public reports."
        actions={
          <Button
            size="sm"
            variant="bordered"
            startContent={<RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />}
            onPress={() => void load()}
            isDisabled={refreshing}
          >
            Refresh
          </Button>
        }
      />

      {/* Current status */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          {data.verified ? (
            <ShieldCheck className="w-5 h-5 text-success" />
          ) : (
            <ShieldAlert className="w-5 h-5 text-default-400" />
          )}
          <h2 className="text-base font-semibold">Current status</h2>
        </CardHeader>
        <Divider />
        <CardBody>
          {data.verified && active ? (
            <div className="space-y-2">
              <div className="flex items-center gap-3">
                <StatusChip status="verified" />
                <span className="text-sm text-default-700">{active.domain}</span>
              </div>
              {active.verified_at && (
                <p className="text-xs text-default-500">
                  Verified on {new Date(active.verified_at).toLocaleString()}
                </p>
              )}
              {active.attestation_note && (
                <p className="text-sm text-default-600 italic">"{active.attestation_note}"</p>
              )}
            </div>
          ) : (
            <p className="text-sm text-default-500">
              This tenant is not currently verified. Use the options below to start a DNS verification or
              apply a manual admin attestation.
            </p>
          )}
        </CardBody>
      </Card>

      {/* New verification request */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Globe className="w-5 h-5 text-primary" />
          <h2 className="text-base font-semibold">Request verification</h2>
        </CardHeader>
        <Divider />
        <CardBody>
          <Tabs aria-label="Verification method">
            <Tab key="dns" title="DNS TXT (preferred)">
              <div className="space-y-3 pt-3">
                <p className="text-sm text-default-600">
                  Provide the official municipality domain you control. We'll generate a DNS TXT record
                  for you to publish; once it propagates, the system marks this tenant as verified.
                </p>
                <Input
                  label="Municipality domain"
                  placeholder="e.g. zurich.ch"
                  value={dnsDomain}
                  onValueChange={setDnsDomain}
                  startContent={<Globe className="w-4 h-4 text-default-400" />}
                />
                <div className="flex justify-end">
                  <Button
                    color="primary"
                    onPress={() => void handleStartDns()}
                    isLoading={submittingDns}
                  >
                    Generate DNS token
                  </Button>
                </div>
              </div>
            </Tab>
            <Tab key="attest" title="Admin attestation">
              <div className="space-y-3 pt-3">
                <p className="text-sm text-default-600">
                  Use this only when DNS control is not available. The verification is recorded as
                  manually attested by an admin.
                </p>
                <Input
                  label="Domain or organisation"
                  placeholder="e.g. zurich.ch"
                  value={attestDomain}
                  onValueChange={setAttestDomain}
                />
                <Textarea
                  label="Attestation note (optional)"
                  description="Why are you attesting this without DNS verification? (Audit trail.)"
                  minRows={2}
                  value={attestNote}
                  onValueChange={setAttestNote}
                />
                <div className="flex justify-end">
                  <Button
                    color="warning"
                    startContent={<Stamp className="w-4 h-4" />}
                    onPress={() => void handleAttest()}
                    isLoading={submittingAttest}
                  >
                    Apply attestation
                  </Button>
                </div>
              </div>
            </Tab>
          </Tabs>
        </CardBody>
      </Card>

      {/* History / pending DNS records */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <ShieldCheck className="w-5 h-5 text-default-500" />
          <h2 className="text-base font-semibold">Verifications</h2>
          <Chip size="sm" variant="flat" className="ml-auto">
            {items.length}
          </Chip>
        </CardHeader>
        <Divider />
        <CardBody className="p-0">
          {items.length === 0 ? (
            <div className="text-center py-12 text-sm text-default-500">
              No verification records yet.
            </div>
          ) : (
            <div className="divide-y divide-default-200">
              {items.map((item) => (
                <div key={item.id} className="px-4 py-4 space-y-2">
                  <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                      <div className="flex items-center gap-2">
                        <StatusChip status={item.status} />
                        <span className="font-medium">{item.domain}</span>
                        <span className="text-xs text-default-500">via {item.method}</span>
                      </div>
                      <p className="text-xs text-default-500 mt-1">
                        Updated {new Date(item.updated_at).toLocaleString()}
                      </p>
                    </div>
                    {item.status !== 'revoked' && (
                      <Button
                        size="sm"
                        variant="flat"
                        color="danger"
                        startContent={<Trash2 className="w-3.5 h-3.5" />}
                        onPress={() => {
                          setRevokeTarget(item);
                          openRevoke();
                        }}
                      >
                        Revoke
                      </Button>
                    )}
                  </div>

                  {item.status === 'pending' && item.dns_record_name && item.dns_record_value && (
                    <div className="bg-default-50 border border-default-200 rounded-md p-3 text-sm space-y-2">
                      <p className="font-medium">Publish this TXT record on your DNS, then wait for propagation:</p>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <div>
                          <p className="text-xs text-default-500 uppercase tracking-wide">Record name</p>
                          <Code className="text-xs">{item.dns_record_name}</Code>
                        </div>
                        <div>
                          <p className="text-xs text-default-500 uppercase tracking-wide">Value</p>
                          <Code className="text-xs break-all">{item.dns_record_value}</Code>
                        </div>
                      </div>
                      <p className="text-xs text-default-500">
                        Type: <Code className="text-xs">TXT</Code> · TTL: <Code className="text-xs">300</Code>
                      </p>
                    </div>
                  )}

                  {item.attestation_note && (
                    <p className="text-sm text-default-600 italic">"{item.attestation_note}"</p>
                  )}
                </div>
              ))}
            </div>
          )}
        </CardBody>
      </Card>

      {/* Revoke modal */}
      <Modal isOpen={revokeOpen} onClose={closeRevoke}>
        <ModalContent>
          <ModalHeader>Revoke verification?</ModalHeader>
          <ModalBody>
            {revokeTarget && (
              <p className="text-sm text-default-600">
                Revoke verification for <strong>{revokeTarget.domain}</strong>? This removes the verified
                badge and the verification record will be marked as revoked.
              </p>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={closeRevoke}>
              Cancel
            </Button>
            <Button color="danger" onPress={() => void handleRevoke()} isLoading={submittingRevoke}>
              Revoke
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
