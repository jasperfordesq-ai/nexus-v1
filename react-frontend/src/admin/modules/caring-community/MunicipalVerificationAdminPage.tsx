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
 * Admin UI text is translated through the admin namespace.
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
import Info from 'lucide-react/icons/info';
import ShieldCheck from 'lucide-react/icons/shield-check';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Globe from 'lucide-react/icons/globe';
import Stamp from 'lucide-react/icons/stamp';
import Trash2 from 'lucide-react/icons/trash-2';
import { useTranslation } from 'react-i18next';
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
  const { t } = useTranslation('admin');
  const toast = useToast();
  usePageTitle(t('municipal_verification.meta.page_title'));

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
        toast.error(res.error || t('municipal_verification.toasts.load_failed'));
      }
    } catch (err) {
      logError('MunicipalVerificationAdminPage: load failed', err);
      toast.error(t('municipal_verification.toasts.load_failed'));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [toast, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleStartDns = useCallback(async () => {
    if (!dnsDomain.trim()) {
      toast.error(t('municipal_verification.validation.domain_required'));
      return;
    }
    setSubmittingDns(true);
    try {
      const res = await api.post<{ verification: VerificationItem }>(
        '/v2/admin/reports/municipal-impact/verification/dns',
        { domain: dnsDomain.trim() },
      );
      if (res.success) {
        toast.success(t('municipal_verification.toasts.dns_generated'));
        setDnsDomain('');
        void load();
      } else {
        toast.error(res.error || t('municipal_verification.toasts.dns_failed'));
      }
    } catch (err) {
      logError('MunicipalVerificationAdminPage: start DNS failed', err);
      toast.error(t('municipal_verification.toasts.dns_failed'));
    } finally {
      setSubmittingDns(false);
    }
  }, [dnsDomain, toast, load, t]);

  const handleAttest = useCallback(async () => {
    if (!attestDomain.trim()) {
      toast.error(t('municipal_verification.validation.domain_required'));
      return;
    }
    setSubmittingAttest(true);
    try {
      const res = await api.post('/v2/admin/reports/municipal-impact/verification/attest', {
        domain: attestDomain.trim(),
        attestation_note: attestNote.trim(),
      });
      if (res.success) {
        toast.success(t('municipal_verification.toasts.attested'));
        setAttestDomain('');
        setAttestNote('');
        void load();
      } else {
        toast.error(res.error || t('municipal_verification.toasts.attest_failed'));
      }
    } catch (err) {
      logError('MunicipalVerificationAdminPage: attest failed', err);
      toast.error(t('municipal_verification.toasts.attest_failed'));
    } finally {
      setSubmittingAttest(false);
    }
  }, [attestDomain, attestNote, toast, load, t]);

  const handleRevoke = useCallback(async () => {
    if (!revokeTarget) return;
    setSubmittingRevoke(true);
    try {
      const res = await api.post(
        `/v2/admin/reports/municipal-impact/verification/${revokeTarget.id}/revoke`,
        {},
      );
      if (res.success) {
        toast.success(t('municipal_verification.toasts.revoked'));
        closeRevoke();
        setRevokeTarget(null);
        void load();
      } else {
        toast.error(res.error || t('municipal_verification.toasts.revoke_failed'));
      }
    } catch (err) {
      logError('MunicipalVerificationAdminPage: revoke failed', err);
      toast.error(t('municipal_verification.toasts.revoke_failed'));
    } finally {
      setSubmittingRevoke(false);
    }
  }, [revokeTarget, toast, load, closeRevoke, t]);

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
          {t('municipal_verification.status.verified')}
        </Chip>
      );
    }
    if (status === 'pending') {
      return (
        <Chip color="warning" variant="flat" size="sm">
          {t('municipal_verification.status.pending_dns')}
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
        title={t('municipal_verification.meta.title')}
        description={t('municipal_verification.meta.description')}
        actions={
          <Button
            size="sm"
            variant="bordered"
            startContent={<RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />}
            onPress={() => void load()}
            isDisabled={refreshing}
          >
            {t('municipal_verification.actions.refresh')}
          </Button>
        }
      />

      {/* Intro card */}
      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">{t('municipal_verification.about.title')}</p>
              <p className="text-default-600">
                {t('municipal_verification.about.body')}
              </p>
              <div className="space-y-0.5 pt-1 text-default-500">
                <p><strong>{t('municipal_verification.status.pending_dns')}:</strong> {t('municipal_verification.about.pending_dns')}</p>
                <p><strong>{t('municipal_verification.status.verified')}:</strong> {t('municipal_verification.about.verified')}</p>
                <p><strong>{t('municipal_verification.status.revoked')}:</strong> {t('municipal_verification.about.revoked')}</p>
              </div>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Current status */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          {data.verified ? (
            <ShieldCheck className="w-5 h-5 text-success" />
          ) : (
            <ShieldAlert className="w-5 h-5 text-default-400" />
          )}
          <h2 className="text-base font-semibold">{t('municipal_verification.current.title')}</h2>
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
                  {t('municipal_verification.current.verified_on', { date: new Date(active.verified_at).toLocaleString() })}
                </p>
              )}
              {active.attestation_note && (
                <p className="text-sm text-default-600 italic">"{active.attestation_note}"</p>
              )}
            </div>
          ) : (
            <p className="text-sm text-default-500">
              {t('municipal_verification.current.not_verified')}
            </p>
          )}
        </CardBody>
      </Card>

      {/* New verification request */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Globe className="w-5 h-5 text-primary" />
          <h2 className="text-base font-semibold">{t('municipal_verification.request.title')}</h2>
        </CardHeader>
        <Divider />
        <CardBody>
          <Tabs aria-label={t('municipal_verification.request.method_aria')}>
            <Tab key="dns" title={t('municipal_verification.request.dns_tab')}>
              <div className="space-y-3 pt-3">
                <p className="text-sm text-default-600">
                  {t('municipal_verification.request.dns_body')}
                </p>
                <Input
                  label={t('municipal_verification.fields.municipality_domain')}
                  placeholder={t('municipal_verification.fields.domain_placeholder')}
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
                    {t('municipal_verification.actions.generate_dns_token')}
                  </Button>
                </div>
              </div>
            </Tab>
            <Tab key="attest" title={t('municipal_verification.request.attestation_tab')}>
              <div className="space-y-3 pt-3">
                <p className="text-sm text-default-600">
                  {t('municipal_verification.request.attestation_body')}
                </p>
                <Input
                  label={t('municipal_verification.fields.domain_or_organisation')}
                  placeholder={t('municipal_verification.fields.domain_placeholder')}
                  value={attestDomain}
                  onValueChange={setAttestDomain}
                />
                <Textarea
                  label={t('municipal_verification.fields.attestation_note')}
                  description={t('municipal_verification.fields.attestation_description')}
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
                    {t('municipal_verification.actions.apply_attestation')}
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
          <h2 className="text-base font-semibold">{t('municipal_verification.history.title')}</h2>
          <Chip size="sm" variant="flat" className="ml-auto">
            {items.length}
          </Chip>
        </CardHeader>
        <Divider />
        <CardBody className="p-0">
          {items.length === 0 ? (
            <div className="text-center py-12 text-sm text-default-500">
              {t('municipal_verification.history.empty')}
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
                        <span className="text-xs text-default-500">{t('municipal_verification.history.via_method', { method: item.method })}</span>
                      </div>
                      <p className="text-xs text-default-500 mt-1">
                        {t('municipal_verification.history.updated', { date: new Date(item.updated_at).toLocaleString() })}
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
                        {t('municipal_verification.actions.revoke')}
                      </Button>
                    )}
                  </div>

                  {item.status === 'pending' && item.dns_record_name && item.dns_record_value && (
                    <div className="bg-default-50 border border-default-200 rounded-md p-3 text-sm space-y-2">
                      <p className="font-medium">{t('municipal_verification.dns.publish_record')}</p>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <div>
                          <p className="text-xs text-default-500 uppercase tracking-wide">{t('municipal_verification.dns.record_name')}</p>
                          <Code className="text-xs">{item.dns_record_name}</Code>
                        </div>
                        <div>
                          <p className="text-xs text-default-500 uppercase tracking-wide">{t('municipal_verification.dns.value')}</p>
                          <Code className="text-xs break-all">{item.dns_record_value}</Code>
                        </div>
                      </div>
                      <p className="text-xs text-default-500">
                        {t('municipal_verification.dns.type_label')}: <Code className="text-xs">{t('municipal_verification.dns.txt_type')}</Code> · {t('municipal_verification.dns.ttl_label')}: <Code className="text-xs">{t('municipal_verification.dns.ttl_value')}</Code>
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
          <ModalHeader>{t('municipal_verification.revoke_modal.title')}</ModalHeader>
          <ModalBody>
            {revokeTarget && (
              <p className="text-sm text-default-600">
                {t('municipal_verification.revoke_modal.body_prefix')}{' '}
                <strong>{revokeTarget.domain}</strong>? {t('municipal_verification.revoke_modal.body_suffix')}
              </p>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={closeRevoke}>
              {t('municipal_verification.actions.cancel')}
            </Button>
            <Button color="danger" onPress={() => void handleRevoke()} isLoading={submittingRevoke}>
              {t('municipal_verification.actions.revoke')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
