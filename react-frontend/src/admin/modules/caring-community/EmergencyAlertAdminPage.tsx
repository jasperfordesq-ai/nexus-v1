// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * EmergencyAlertAdminPage — AG70 Emergency/Safety Alert Tier
 *
 * Admin management console for emergency alerts.
 * Features:
 *  - Table of all alerts (title, severity, sent_at, expires_at, push stats, active/inactive)
 *  - "Send Emergency Alert" modal with title, body, severity, optional expires_at
 *  - Mandatory confirmation checkbox before broadcast
 *  - Per-active-alert deactivate button with confirmation
 */

import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Checkbox,
  Chip,
  Divider,
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
  Input,
  useDisclosure,
} from '@heroui/react';
import AlertTriangle from 'lucide-react/icons/alert-triangle';
import Bell from 'lucide-react/icons/bell';
import BellOff from 'lucide-react/icons/bell-off';
import Info from 'lucide-react/icons/info';
import Send from 'lucide-react/icons/send';
import api from '@/lib/api';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface EmergencyAlert {
  id: number;
  title: string;
  body: string;
  severity: 'info' | 'warning' | 'danger';
  sent_at: string | null;
  expires_at: string | null;
  is_active: number | boolean;
  push_sent: number | boolean;
  push_result: string | null;
  dismissed_count: number;
  created_at: string;
}

interface PushResult {
  sent: number;
  failed: number;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function parsePushResult(raw: string | null): PushResult | null {
  if (!raw) return null;
  try {
    return typeof raw === 'string' ? JSON.parse(raw) : (raw as PushResult);
  } catch {
    return null;
  }
}

function formatDate(ts: string | null): string {
  if (!ts) return '-';
  return new Date(ts).toLocaleString();
}

const SEVERITY_OPTIONS = [
  { key: 'info', labelKey: 'caring_emergency.severity.info', color: 'primary' as const },
  { key: 'warning', labelKey: 'caring_emergency.severity.warning', color: 'warning' as const },
  { key: 'danger', labelKey: 'caring_emergency.severity.danger', color: 'danger' as const },
];

function severityChip(severity: string, label: string) {
  const opt = SEVERITY_OPTIONS.find((o) => o.key === severity);
  return (
    <Chip size="sm" color={opt?.color ?? 'default'} variant="flat">
      {label}
    </Chip>
  );
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export default function EmergencyAlertAdminPage() {
  const { t } = useTranslation('admin');
  const { isOpen, onOpen, onClose } = useDisclosure();

  const [alerts, setAlerts] = useState<EmergencyAlert[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Form state
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [severity, setSeverity] = useState<'info' | 'warning' | 'danger'>('warning');
  const [expiresAt, setExpiresAt] = useState('');
  const [confirmed, setConfirmed] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  // Deactivate confirm state
  const [deactivatingId, setDeactivatingId] = useState<number | null>(null);

  const fetchAlerts = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get<{ data: EmergencyAlert[] }>(
        '/v2/admin/caring-community/emergency-alerts',
      );
      const raw = res.data;
      const list: EmergencyAlert[] = Array.isArray(raw)
        ? raw
        : (Array.isArray((raw as { data?: EmergencyAlert[] }).data)
            ? (raw as { data: EmergencyAlert[] }).data
            : []);
      setAlerts(list);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : t('caring_emergency.errors.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    void fetchAlerts();
  }, [fetchAlerts]);

  const handleOpenModal = () => {
    setTitle('');
    setBody('');
    setSeverity('warning');
    setExpiresAt('');
    setConfirmed(false);
    setSubmitError(null);
    onOpen();
  };

  const handleStore = async () => {
    if (!title.trim() || !body.trim() || !confirmed) return;
    setSubmitting(true);
    setSubmitError(null);
    try {
      await api.post('/v2/admin/caring-community/emergency-alerts', {
        title: title.trim(),
        body: body.trim(),
        severity,
        expires_at: expiresAt || null,
      });
      onClose();
      await fetchAlerts();
    } catch (e: unknown) {
      setSubmitError(e instanceof Error ? e.message : t('caring_emergency.errors.send_failed'));
    } finally {
      setSubmitting(false);
    }
  };

  const handleDeactivate = async (id: number) => {
    try {
      await api.delete(`/v2/admin/caring-community/emergency-alerts/${id}`);
      setDeactivatingId(null);
      await fetchAlerts();
    } catch {
      setDeactivatingId(null);
    }
  };

  const severityLabel = useCallback((value: string) => {
    const option = SEVERITY_OPTIONS.find((item) => item.key === value);
    return option ? t(option.labelKey) : value;
  }, [t]);

  return (
    <>
      <Card>
        <CardHeader className="flex items-center justify-between gap-4 flex-wrap">
          <div className="flex items-center gap-2">
            <AlertTriangle size={20} className="text-danger" />
            <h2 className="text-lg font-semibold">{t('caring_emergency.title')}</h2>
          </div>
          <Button
            color="danger"
            startContent={<Send size={16} />}
            onPress={handleOpenModal}
          >
            {t('caring_emergency.actions.open_send_modal')}
          </Button>
        </CardHeader>
        <Divider />
        <CardBody>
          {loading && (
            <div className="flex justify-center py-10">
              <Spinner size="lg" />
            </div>
          )}

          {!loading && error && (
            <p className="text-danger text-sm">{error}</p>
          )}

          {!loading && !error && alerts.length === 0 && (
            <p className="text-default-400 text-sm py-4">{t('caring_emergency.empty')}</p>
          )}

          {!loading && !error && alerts.length > 0 && (
            <Table aria-label={t('caring_emergency.table.aria')} removeWrapper>
              <TableHeader>
                <TableColumn>{t('caring_emergency.table.title')}</TableColumn>
                <TableColumn>{t('caring_emergency.table.severity')}</TableColumn>
                <TableColumn>{t('caring_emergency.table.sent')}</TableColumn>
                <TableColumn>{t('caring_emergency.table.expires')}</TableColumn>
                <TableColumn>{t('caring_emergency.table.push')}</TableColumn>
                <TableColumn>{t('caring_emergency.table.dismissed')}</TableColumn>
                <TableColumn>{t('caring_emergency.table.status')}</TableColumn>
                <TableColumn>{t('caring_emergency.table.actions')}</TableColumn>
              </TableHeader>
              <TableBody>
                {alerts.map((alert) => {
                  const pushResult = parsePushResult(alert.push_result);
                  const isActive = Boolean(alert.is_active);
                  return (
                    <TableRow key={alert.id}>
                      <TableCell>
                        <div>
                          <p className="font-medium text-sm">{alert.title}</p>
                          <p className="text-default-400 text-xs line-clamp-1 max-w-xs">
                            {alert.body}
                          </p>
                        </div>
                      </TableCell>
                      <TableCell>{severityChip(alert.severity, severityLabel(alert.severity))}</TableCell>
                      <TableCell>
                        <span className="text-xs">{formatDate(alert.sent_at)}</span>
                      </TableCell>
                      <TableCell>
                        <span className="text-xs">{formatDate(alert.expires_at)}</span>
                      </TableCell>
                      <TableCell>
                        {alert.push_sent ? (
                          <div className="flex items-center gap-1">
                            <Bell size={14} className="text-success" />
                            {pushResult ? (
                              <span className="text-xs">
                                {pushResult.sent}↑ {pushResult.failed > 0 && (
                                  <span className="text-danger">{pushResult.failed}↓</span>
                                )}
                              </span>
                            ) : (
                              <span className="text-xs text-success">{t('caring_emergency.push.sent')}</span>
                            )}
                          </div>
                        ) : (
                          <div className="flex items-center gap-1">
                            <BellOff size={14} className="text-default-400" />
                            <span className="text-xs text-default-400">-</span>
                          </div>
                        )}
                      </TableCell>
                      <TableCell>
                        <span className="text-sm">{alert.dismissed_count}</span>
                      </TableCell>
                      <TableCell>
                        <Chip
                          size="sm"
                          color={isActive ? 'success' : 'default'}
                          variant="flat"
                        >
                          {isActive ? t('active') : t('caring_emergency.status.inactive')}
                        </Chip>
                      </TableCell>
                      <TableCell>
                        {isActive && (
                          <>
                            {deactivatingId === alert.id ? (
                              <div className="flex items-center gap-2">
                                <span className="text-xs text-warning">{t('caring_emergency.actions.confirm_prompt')}</span>
                                <Button
                                  size="sm"
                                  color="danger"
                                  variant="flat"
                                  onPress={() => void handleDeactivate(alert.id)}
                                >
                                  {t('caring_emergency.actions.confirm_deactivate')}
                                </Button>
                                <Button
                                  size="sm"
                                  variant="flat"
                                  onPress={() => setDeactivatingId(null)}
                                >
                                  {t('caring_emergency.actions.cancel')}
                                </Button>
                              </div>
                            ) : (
                              <Button
                                size="sm"
                                color="warning"
                                variant="flat"
                                onPress={() => setDeactivatingId(alert.id)}
                              >
                                {t('caring_emergency.actions.deactivate')}
                              </Button>
                            )}
                          </>
                        )}
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      <Modal
        isOpen={isOpen}
        onClose={onClose}
        size="lg"
        isDismissable={!submitting}
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <AlertTriangle size={20} className="text-danger" />
            {t('caring_emergency.modal.title')}
          </ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label={t('caring_emergency.form.title')}
              placeholder={t('caring_emergency.form.title_placeholder')}
              value={title}
              onValueChange={setTitle}
              isRequired
              variant="bordered"
              maxLength={255}
            />
            <Textarea
              label={t('caring_emergency.form.body')}
              placeholder={t('caring_emergency.form.body_placeholder')}
              value={body}
              onValueChange={setBody}
              isRequired
              variant="bordered"
              minRows={3}
              maxRows={8}
              maxLength={2000}
            />
            <Select
              label={t('caring_emergency.form.severity')}
              selectedKeys={[severity]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as 'info' | 'warning' | 'danger';
                if (val) setSeverity(val);
              }}
              variant="bordered"
            >
              {SEVERITY_OPTIONS.map((opt) => (
                <SelectItem key={opt.key} textValue={t(opt.labelKey)}>
                  <div className="flex items-center gap-2">
                    {opt.key === 'info' ? (
                      <Info size={14} className="text-primary" />
                    ) : (
                      <AlertTriangle
                        size={14}
                        className={opt.key === 'danger' ? 'text-danger' : 'text-warning'}
                      />
                    )}
                    {t(opt.labelKey)}
                  </div>
                </SelectItem>
              ))}
            </Select>
            <Input
              label={t('caring_emergency.form.expires_at')}
              type="datetime-local"
              value={expiresAt}
              onValueChange={setExpiresAt}
              variant="bordered"
              description={t('caring_emergency.form.expires_at_description')}
            />
            <Divider />
            <Checkbox
              isSelected={confirmed}
              onValueChange={setConfirmed}
              color="danger"
            >
              <span className="text-sm font-medium">
                {t('caring_emergency.form.confirm_prefix')}{' '}
                <strong>{t('caring_emergency.form.confirm_audience')}</strong>{' '}
                {t('caring_emergency.form.confirm_suffix')}
              </span>
            </Checkbox>
            {submitError && (
              <p className="text-danger text-sm">{submitError}</p>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} isDisabled={submitting}>
              {t('caring_emergency.actions.cancel')}
            </Button>
            <Button
              color="danger"
              startContent={<Send size={16} />}
              onPress={() => void handleStore()}
              isLoading={submitting}
              isDisabled={!title.trim() || !body.trim() || !confirmed || submitting}
            >
              {t('caring_emergency.actions.broadcast')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}
