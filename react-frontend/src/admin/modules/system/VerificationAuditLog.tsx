// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * VerificationAuditLog — Admin component showing identity verification events.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card, CardBody, CardHeader, Select, SelectItem, Button, Spinner, Chip,
  Table, TableHeader, TableColumn, TableBody, TableRow, TableCell,
} from '@heroui/react';
import { ScrollText, ChevronLeft, ChevronRight, RefreshCw } from 'lucide-react';
import { api } from '@/lib/api';

interface AuditEvent {
  id: number;
  user_id: number;
  session_id: number | null;
  event_type: string;
  actor_type: string;
  actor_id: number | null;
  details: string | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
  first_name: string | null;
  last_name: string | null;
  user_email: string | null;
}

const EVENT_TYPE_LABELS: Record<string, { label: string; color: 'success' | 'danger' | 'warning' | 'primary' | 'default' }> = {
  registration_started: { label: 'Registration', color: 'primary' },
  verification_created: { label: 'Session Created', color: 'default' },
  verification_started: { label: 'Started', color: 'primary' },
  verification_processing: { label: 'Processing', color: 'warning' },
  verification_passed: { label: 'Passed', color: 'success' },
  verification_failed: { label: 'Failed', color: 'danger' },
  verification_expired: { label: 'Expired', color: 'warning' },
  verification_cancelled: { label: 'Cancelled', color: 'default' },
  admin_review_started: { label: 'Admin Review', color: 'warning' },
  admin_approved: { label: 'Admin Approved', color: 'success' },
  admin_rejected: { label: 'Admin Rejected', color: 'danger' },
  account_activated: { label: 'Activated', color: 'success' },
  fallback_triggered: { label: 'Fallback', color: 'warning' },
};

const EVENT_TYPES = Object.keys(EVENT_TYPE_LABELS);
const PAGE_SIZE = 25;

export function VerificationAuditLog() {
  const [events, setEvents] = useState<AuditEvent[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(0);
  const [filterType, setFilterType] = useState<string>('');

  const fetchEvents = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        limit: String(PAGE_SIZE),
        offset: String(page * PAGE_SIZE),
      });
      if (filterType) params.set('event_type', filterType);

      const res = await api.get<{ events: AuditEvent[]; total: number }>(
        `/v2/admin/identity/audit-log?${params.toString()}`
      );
      if (res.success && res.data) {
        setEvents(res.data.events || []);
        setTotal(res.data.total || 0);
      }
    } catch {
      // silent
    } finally {
      setLoading(false);
    }
  }, [page, filterType]);

  useEffect(() => {
    fetchEvents();
  }, [fetchEvents]);

  const totalPages = Math.ceil(total / PAGE_SIZE);

  return (
    <Card className="shadow-sm">
      <CardHeader className="flex flex-col sm:flex-row gap-3 justify-between items-start sm:items-center px-6 pt-5 pb-0">
        <div className="flex items-center gap-2">
          <ScrollText className="w-5 h-5 text-indigo-500" />
          <h3 className="text-lg font-semibold">Verification Audit Log</h3>
          <Chip size="sm" variant="flat">{total} events</Chip>
        </div>
        <div className="flex items-center gap-2">
          <Select
            size="sm"
            placeholder="All events"
            className="w-48"
            selectedKeys={filterType ? [filterType] : []}
            onSelectionChange={(keys) => {
              const key = Array.from(keys as Set<string>)[0] || '';
              setFilterType(key);
              setPage(0);
            }}
          >
            {EVENT_TYPES.map((type) => (
              <SelectItem key={type} textValue={EVENT_TYPE_LABELS[type].label}>
                {EVENT_TYPE_LABELS[type].label}
              </SelectItem>
            ))}
          </Select>
          <Button isIconOnly size="sm" variant="flat" onPress={fetchEvents} aria-label="Refresh">
            <RefreshCw className="w-4 h-4" />
          </Button>
        </div>
      </CardHeader>
      <CardBody className="px-6 pb-5">
        {loading ? (
          <div className="flex justify-center py-8">
            <Spinner size="lg" />
          </div>
        ) : events.length === 0 ? (
          <p className="text-center py-8 text-theme-muted">No verification events yet.</p>
        ) : (
          <>
            <Table aria-label="Verification audit log" removeWrapper>
              <TableHeader>
                <TableColumn>TIME</TableColumn>
                <TableColumn>USER</TableColumn>
                <TableColumn>EVENT</TableColumn>
                <TableColumn>ACTOR</TableColumn>
                <TableColumn>IP</TableColumn>
                <TableColumn>DETAILS</TableColumn>
              </TableHeader>
              <TableBody>
                {events.map((event) => {
                  const typeInfo = EVENT_TYPE_LABELS[event.event_type] || { label: event.event_type, color: 'default' as const };
                  const userName = [event.first_name, event.last_name].filter(Boolean).join(' ') || `User #${event.user_id}`;
                  let details = '';
                  if (event.details) {
                    try {
                      const parsed = JSON.parse(event.details);
                      details = Object.entries(parsed)
                        .map(([k, v]) => `${k}: ${v}`)
                        .join(', ');
                    } catch {
                      details = event.details;
                    }
                  }

                  return (
                    <TableRow key={event.id}>
                      <TableCell className="whitespace-nowrap text-xs text-theme-muted">
                        {new Date(event.created_at).toLocaleString()}
                      </TableCell>
                      <TableCell>
                        <div className="text-sm font-medium">{userName}</div>
                        {event.user_email && <div className="text-xs text-theme-muted">{event.user_email}</div>}
                      </TableCell>
                      <TableCell>
                        <Chip size="sm" color={typeInfo.color} variant="flat">
                          {typeInfo.label}
                        </Chip>
                      </TableCell>
                      <TableCell className="text-xs text-theme-muted capitalize">
                        {event.actor_type}
                      </TableCell>
                      <TableCell className="text-xs text-theme-muted font-mono">
                        {event.ip_address || '—'}
                      </TableCell>
                      <TableCell className="text-xs text-theme-muted max-w-[200px] truncate" title={details}>
                        {details || '—'}
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex justify-between items-center mt-4">
                <span className="text-sm text-theme-muted">
                  Page {page + 1} of {totalPages}
                </span>
                <div className="flex gap-2">
                  <Button
                    size="sm"
                    variant="flat"
                    isDisabled={page === 0}
                    onPress={() => setPage((p) => p - 1)}
                    startContent={<ChevronLeft className="w-4 h-4" />}
                  >
                    Previous
                  </Button>
                  <Button
                    size="sm"
                    variant="flat"
                    isDisabled={page >= totalPages - 1}
                    onPress={() => setPage((p) => p + 1)}
                    endContent={<ChevronRight className="w-4 h-4" />}
                  >
                    Next
                  </Button>
                </div>
              </div>
            )}
          </>
        )}
      </CardBody>
    </Card>
  );
}

export default VerificationAuditLog;
