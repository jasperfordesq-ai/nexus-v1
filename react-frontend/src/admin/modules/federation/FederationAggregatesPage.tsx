// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Aggregates — admin self-service for cross-node aggregate sharing.
 *
 * Lets a tenant admin enable/disable the public /federation/aggregates feed,
 * rotate the HMAC signing secret, preview the JSON payload that would be
 * exposed, and inspect the audit trail of recent queries.
 *
 * Implements the consent surface described in
 * docs/CARING_COMMUNITY_ARCHITECTURE.md (R1+R2).
 *
 */

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
  Spinner,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
} from '@heroui/react';
import Eye from 'lucide-react/icons/eye';
import FileSearch from 'lucide-react/icons/file-search';
import KeyRound from 'lucide-react/icons/key-round';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ShieldCheck from 'lucide-react/icons/shield-check';

import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminFederation } from '../../api/adminApi';
import { PageHeader } from '../../components';

interface ConsentState {
  enabled: boolean;
  has_secret: boolean;
  last_rotated_at: string | null;
}

interface AuditEntry {
  id: number;
  requester_origin: string | null;
  period_from: string;
  period_to: string;
  fields_returned: unknown;
  signature_snippet: string;
  created_at: string;
}

function unwrapData<T>(res: { data?: unknown }): T | null {
  const payload = res?.data as unknown;
  if (payload && typeof payload === 'object' && 'data' in (payload as Record<string, unknown>)) {
    return (payload as { data: T }).data;
  }
  return (payload as T) ?? null;
}

export default function FederationAggregatesPage() {
  const { t } = useTranslation('admin');
  usePageTitle(t('federation_aggregates.meta.title'));
  const toast = useToast();

  const [consent, setConsent] = useState<ConsentState | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [rotating, setRotating] = useState(false);

  const [auditOpen, setAuditOpen] = useState(false);
  const [auditEntries, setAuditEntries] = useState<AuditEntry[]>([]);
  const [auditLoading, setAuditLoading] = useState(false);

  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewData, setPreviewData] = useState<Record<string, unknown> | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);

  const loadConsent = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminFederation.getAggregateConsent();
      const data = unwrapData<ConsentState>(res);
      setConsent(
        data ?? { enabled: false, has_secret: false, last_rotated_at: null }
      );
    } catch {
      toast.error(t('federation_aggregates.toasts.load_consent_failed'));
      setConsent({ enabled: false, has_secret: false, last_rotated_at: null });
    }
    setLoading(false);
  }, [t, toast]);

  useEffect(() => {
    loadConsent();
  }, [loadConsent]);

  const handleToggle = useCallback(
    async (next: boolean) => {
      setSaving(true);
      try {
        const res = await adminFederation.updateAggregateConsent(next);
        const data = unwrapData<ConsentState>(res);
        if (data) {
          setConsent(data);
          toast.success(
            next
              ? t('federation_aggregates.toasts.enabled')
              : t('federation_aggregates.toasts.disabled')
          );
        }
      } catch {
        toast.error(t('federation_aggregates.toasts.update_failed'));
      }
      setSaving(false);
    },
    [t, toast]
  );

  const handleRotate = useCallback(async () => {
    setRotating(true);
    try {
      const res = await adminFederation.rotateAggregateSecret();
      const data = unwrapData<{ rotated: boolean; consent: ConsentState }>(res);
      if (data?.consent) {
        setConsent(data.consent);
        toast.success(t('federation_aggregates.toasts.secret_rotated'));
      }
    } catch {
      toast.error(t('federation_aggregates.toasts.rotate_failed'));
    }
    setRotating(false);
  }, [t, toast]);

  const openAudit = useCallback(async () => {
    setAuditOpen(true);
    setAuditLoading(true);
    try {
      const res = await adminFederation.getAggregateAuditLog();
      const data = unwrapData<{ entries: AuditEntry[] }>(res);
      setAuditEntries(data?.entries ?? []);
    } catch {
      toast.error(t('federation_aggregates.toasts.load_audit_failed'));
      setAuditEntries([]);
    }
    setAuditLoading(false);
  }, [t, toast]);

  const openPreview = useCallback(async () => {
    setPreviewOpen(true);
    setPreviewLoading(true);
    try {
      const res = await adminFederation.getAggregatePreview();
      const data = unwrapData<{ payload: Record<string, unknown>; algorithm: string }>(res);
      setPreviewData(data?.payload ?? null);
    } catch {
      toast.error(t('federation_aggregates.toasts.load_preview_failed'));
      setPreviewData(null);
    }
    setPreviewLoading(false);
  }, [t, toast]);

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('federation_aggregates.meta.title')}
        description={t('federation_aggregates.meta.description')}
      />

      <Card>
        <CardHeader className="flex items-center gap-2">
          <ShieldCheck className="h-5 w-5" aria-hidden="true" />
          <span className="font-semibold">{t('federation_aggregates.consent.title')}</span>
        </CardHeader>
        <CardBody className="space-y-4">
          {loading ? (
            <div className="flex items-center gap-2">
              <Spinner size="sm" /> {t('federation_aggregates.consent.loading')}
            </div>
          ) : (
            <>
              <div className="flex items-center justify-between gap-4">
                <div>
                  <div className="font-medium">{t('federation_aggregates.consent.enable_label')}</div>
                  <div className="text-sm text-default-500">
                    {t('federation_aggregates.consent.endpoint_prefix')} <code>/api/v2/federation/aggregates</code>{' '}
                    {t('federation_aggregates.consent.endpoint_suffix')}
                  </div>
                </div>
                <Switch
                  isSelected={!!consent?.enabled}
                  isDisabled={saving}
                  onValueChange={handleToggle}
                  aria-label={t('federation_aggregates.consent.enable_label')}
                />
              </div>

              <div className="flex flex-wrap items-center gap-3 text-sm">
                <Chip
                  color={consent?.enabled ? 'success' : 'default'}
                  variant="flat"
                  size="sm"
                >
                  {consent?.enabled ? t('federation_aggregates.status.enabled') : t('federation_aggregates.status.disabled')}
                </Chip>
                <Chip
                  color={consent?.has_secret ? 'primary' : 'warning'}
                  variant="flat"
                  size="sm"
                >
                  {consent?.has_secret ? t('federation_aggregates.status.signing_secret_present') : t('federation_aggregates.status.no_signing_secret')}
                </Chip>
                {consent?.last_rotated_at ? (
                  <span className="text-default-500">
                    {t('federation_aggregates.status.last_rotated', { date: new Date(consent.last_rotated_at).toLocaleString() })}
                  </span>
                ) : (
                  <span className="text-default-500">{t('federation_aggregates.status.never_rotated')}</span>
                )}
              </div>

              <div className="flex flex-wrap gap-2 pt-2">
                <Button
                  color="primary"
                  variant="flat"
                  startContent={<KeyRound className="h-4 w-4" aria-hidden="true" />}
                  isLoading={rotating}
                  onPress={handleRotate}
                >
                  {t('federation_aggregates.actions.rotate_secret')}
                </Button>
                <Button
                  variant="flat"
                  startContent={<Eye className="h-4 w-4" aria-hidden="true" />}
                  onPress={openPreview}
                >
                  {t('federation_aggregates.actions.preview_payload')}
                </Button>
                <Button
                  variant="flat"
                  startContent={<FileSearch className="h-4 w-4" aria-hidden="true" />}
                  onPress={openAudit}
                >
                  {t('federation_aggregates.actions.show_audit_log')}
                </Button>
                <Button
                  variant="light"
                  startContent={<RefreshCw className="h-4 w-4" aria-hidden="true" />}
                  onPress={loadConsent}
                >
                  {t('federation_aggregates.actions.refresh')}
                </Button>
              </div>
            </>
          )}
        </CardBody>
      </Card>

      <Modal
        isOpen={auditOpen}
        onOpenChange={setAuditOpen}
        size="3xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader>{t('federation_aggregates.audit.title')}</ModalHeader>
          <ModalBody>
            {auditLoading ? (
              <div className="flex items-center gap-2">
                <Spinner size="sm" /> {t('federation_aggregates.audit.loading')}
              </div>
            ) : auditEntries.length === 0 ? (
              <div className="text-default-500">{t('federation_aggregates.audit.empty')}</div>
            ) : (
              <Table aria-label={t('federation_aggregates.audit.table_aria')}>
                <TableHeader>
                  <TableColumn>{t('federation_aggregates.audit.columns.time')}</TableColumn>
                  <TableColumn>{t('federation_aggregates.audit.columns.requester')}</TableColumn>
                  <TableColumn>{t('federation_aggregates.audit.columns.period')}</TableColumn>
                  <TableColumn>{t('federation_aggregates.audit.columns.signature')}</TableColumn>
                </TableHeader>
                <TableBody emptyContent={t('federation_aggregates.audit.empty')}>
                  {auditEntries.map((e) => (
                    <TableRow key={e.id}>
                      <TableCell>{new Date(e.created_at).toLocaleString()}</TableCell>
                      <TableCell className="font-mono text-xs">
                        {e.requester_origin ?? t('federation_aggregates.audit.unknown_requester')}
                      </TableCell>
                      <TableCell className="text-xs">
                        {t('federation_aggregates.audit.period_range', { from: e.period_from, to: e.period_to })}
                      </TableCell>
                      <TableCell className="font-mono text-xs">
                        {e.signature_snippet}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={() => setAuditOpen(false)}>
              {t('federation_aggregates.actions.close')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      <Modal
        isOpen={previewOpen}
        onOpenChange={setPreviewOpen}
        size="3xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          <ModalHeader>{t('federation_aggregates.preview.title')}</ModalHeader>
          <ModalBody>
            {previewLoading ? (
              <div className="flex items-center gap-2">
                <Spinner size="sm" /> {t('federation_aggregates.preview.loading')}
              </div>
            ) : previewData ? (
              <pre className="bg-default-100 rounded p-4 text-xs overflow-x-auto whitespace-pre-wrap">
                {JSON.stringify(previewData, null, 2)}
              </pre>
            ) : (
              <div className="text-default-500">{t('federation_aggregates.preview.empty')}</div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={() => setPreviewOpen(false)}>
              {t('federation_aggregates.actions.close')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}
