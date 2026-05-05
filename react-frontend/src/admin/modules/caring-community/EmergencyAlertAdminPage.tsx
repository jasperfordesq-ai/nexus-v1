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
  { key: 'info', label: 'Info', color: 'primary' as const },
  { key: 'warning', label: 'Warning', color: 'warning' as const },
  { key: 'danger', label: 'Danger', color: 'danger' as const },
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
      setError(e instanceof Error ? e.message : 'Failed to load emergency alerts');
    } finally {
      setLoading(false);
    }
  }, []);

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
      const res = await api.post('/v2/admin/caring-community/emergency-alerts', {
        title: title.trim(),
        body: body.trim(),
        severity,
        expires_at: expiresAt || null,
      });
      if (!res.success) {
        throw new Error(res.error ?? 'Failed to broadcast alert');
      }
      onClose();
      await fetchAlerts();
    } catch (e: unknown) {
      setSubmitError(e instanceof Error ? e.message : 'Failed to broadcast alert');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDeactivate = async (id: number) => {
    try {
      const res = await api.delete(`/v2/admin/caring-community/emergency-alerts/${id}`);
      if (!res.success) {
        throw new Error(res.error ?? 'Failed to deactivate alert');
      }
      setDeactivatingId(null);
      await fetchAlerts();
    } catch {
      setDeactivatingId(null);
    }
  };

  const severityLabel = useCallback((value: string) => {
    const option = SEVERITY_OPTIONS.find((item) => item.key === value);
    return option ? option.label : value;
  }, []);

  return (
    <>
      {/* Prominent intro/warning card */}
      <Card className="border-l-4 border-l-danger bg-danger-50 dark:bg-danger-900/20 mb-4" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-danger" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-danger-800 dark:text-danger-200">About this page — use with caution</p>
              <p className="text-default-600">
                Emergency Alerts are urgent broadcasts sent to all members (or a targeted sub-group)
                immediately. Use this only for genuine emergencies — severe weather, community safety
                incidents, or urgent care coverage gaps. Alerts are sent as push notifications and
                in-app banners. They cannot be unsent once dispatched.
              </p>
              <div className="space-y-0.5 pt-1 text-default-500">
                <p><strong>Targeting:</strong> Selecting severity "Danger" or "Warning" sends to all members. Draft carefully — there is no recall once broadcast.</p>
                <p><strong>Delivery:</strong> Alerts are delivered within 60 seconds of dispatch.</p>
              </div>
            </div>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardHeader className="flex items-center justify-between gap-4 flex-wrap">
          <div className="flex items-center gap-2">
            <AlertTriangle size={20} className="text-danger" />
            <h2 className="text-lg font-semibold">Emergency Alerts</h2>
          </div>
          <Button
            color="danger"
            startContent={<Send size={16} />}
            onPress={handleOpenModal}
          >
            Send Emergency Alert
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
            <p className="text-default-400 text-sm py-4">No emergency alerts yet.</p>
          )}

          {!loading && !error && alerts.length > 0 && (
            <Table aria-label="Emergency alerts" removeWrapper>
              <TableHeader>
                <TableColumn>Title</TableColumn>
                <TableColumn>Severity</TableColumn>
                <TableColumn>Sent</TableColumn>
                <TableColumn>Expires</TableColumn>
                <TableColumn>Push</TableColumn>
                <TableColumn>Dismissed</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Actions</TableColumn>
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
                              <span className="text-xs text-success">Sent</span>
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
                          {isActive ? 'Active' : 'Inactive'}
                        </Chip>
                      </TableCell>
                      <TableCell>
                        {isActive && (
                          <>
                            {deactivatingId === alert.id ? (
                              <div className="flex items-center gap-2">
                                <span className="text-xs text-warning">Are you sure?</span>
                                <Button
                                  size="sm"
                                  color="danger"
                                  variant="flat"
                                  onPress={() => void handleDeactivate(alert.id)}
                                >
                                  Confirm deactivate
                                </Button>
                                <Button
                                  size="sm"
                                  variant="flat"
                                  onPress={() => setDeactivatingId(null)}
                                >
                                  Cancel
                                </Button>
                              </div>
                            ) : (
                              <Button
                                size="sm"
                                color="warning"
                                variant="flat"
                                onPress={() => setDeactivatingId(alert.id)}
                              >
                                Deactivate
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
            Send Emergency Alert
          </ModalHeader>
          <ModalBody className="gap-4">
            <Input
              label="Title"
              placeholder="e.g. Severe weather warning"
              value={title}
              onValueChange={setTitle}
              isRequired
              variant="bordered"
              maxLength={255}
            />
            <Textarea
              label="Body"
              placeholder="Describe the situation and any action members should take."
              value={body}
              onValueChange={setBody}
              isRequired
              variant="bordered"
              minRows={3}
              maxRows={8}
              maxLength={2000}
            />
            <Select
              label="Severity"
              selectedKeys={[severity]}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as 'info' | 'warning' | 'danger';
                if (val) setSeverity(val);
              }}
              variant="bordered"
            >
              {SEVERITY_OPTIONS.map((opt) => (
                <SelectItem key={opt.key} textValue={opt.label}>
                  <div className="flex items-center gap-2">
                    {opt.key === 'info' ? (
                      <Info size={14} className="text-primary" />
                    ) : (
                      <AlertTriangle
                        size={14}
                        className={opt.key === 'danger' ? 'text-danger' : 'text-warning'}
                      />
                    )}
                    {opt.label}
                  </div>
                </SelectItem>
              ))}
            </Select>
            <Input
              label="Expires at (optional)"
              type="datetime-local"
              value={expiresAt}
              onValueChange={setExpiresAt}
              variant="bordered"
              description="Leave blank to keep the alert active until manually deactivated."
            />
            <Divider />
            <Checkbox
              isSelected={confirmed}
              onValueChange={setConfirmed}
              color="danger"
            >
              <span className="text-sm font-medium">
                I confirm this will broadcast to{' '}
                <strong>every member of this tenant</strong>{' '}
                via push notification and in-app banner.
              </span>
            </Checkbox>
            {submitError && (
              <p className="text-danger text-sm">{submitError}</p>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} isDisabled={submitting}>
              Cancel
            </Button>
            <Button
              color="danger"
              startContent={<Send size={16} />}
              onPress={() => void handleStore()}
              isLoading={submitting}
              isDisabled={!title.trim() || !body.trim() || !confirmed || submitting}
            >
              Broadcast
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}
