// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
} from '@heroui/react';
import AlertTriangle from 'lucide-react/icons/alert-triangle';
import Clock from 'lucide-react/icons/clock';
import Heart from 'lucide-react/icons/heart';
import ShieldAlert from 'lucide-react/icons/shield-alert';
import Users2 from 'lucide-react/icons/users';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { PageHeader, StatCard } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface SupportRelationship {
  id: number;
  supporter: { id: number; name: string; trust_tier: number };
  type: string;
  hours_logged: number;
  last_activity_at: string | null;
  status: string;
}

interface RecipientCircle {
  recipient: {
    id: number;
    name: string;
    trust_tier: number;
    member_since: string | null;
  };
  support_relationships: SupportRelationship[];
  total_hours_received: number;
  open_help_requests: number;
  safeguarding_flags: number;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function tierColor(tier: number): 'default' | 'warning' | 'success' | 'primary' {
  if (tier >= 4) return 'primary';
  if (tier >= 3) return 'success';
  if (tier >= 2) return 'warning';
  return 'default';
}

const TIER_LABELS = ['Newcomer', 'Member', 'Trusted', 'Verified', 'Coordinator'];

function tierLabel(tier: number): string {
  return TIER_LABELS[Math.min(tier, TIER_LABELS.length - 1)] ?? String(tier);
}

function fmtDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function CareRecipientCirclePage() {
  usePageTitle('Care Recipient Circle');

  const [userIdInput, setUserIdInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [circle, setCircle] = useState<RecipientCircle | null>(null);

  const handleLookup = useCallback(async () => {
    const userId = userIdInput.trim();
    if (!userId) return;

    setLoading(true);
    setError(null);
    setCircle(null);

    try {
      const res = await api.get<RecipientCircle>(
        `/v2/admin/caring-community/recipient/${userId}/circle`,
      );
      setCircle(res.data ?? null);
    } catch {
      setError('Failed to load recipient circle. Check the member ID and try again.');
    } finally {
      setLoading(false);
    }
  }, [userIdInput]);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'Enter') handleLookup();
    },
    [handleLookup],
  );

  const activeCount = circle?.support_relationships.filter((r) => r.status === 'active').length ?? 0;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Care Recipient Circle"
        subtitle="View the full support network around any member"
        icon={<Users2 size={20} />}
      />

      {/* Lookup bar */}
      <Card>
        <CardBody>
          <div className="flex items-end gap-3">
            <Input
              label="Member ID"
              placeholder="Enter member user ID…"
              value={userIdInput}
              onValueChange={setUserIdInput}
              onKeyDown={handleKeyDown}
              variant="bordered"
              className="max-w-xs"
              type="number"
              min={1}
            />
            <Button
              color="primary"
              onPress={handleLookup}
              isLoading={loading}
              isDisabled={!userIdInput.trim()}
            >
              View circle
            </Button>
          </div>
        </CardBody>
      </Card>

      {/* Loading */}
      {loading && (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      )}

      {/* Error */}
      {error && !loading && (
        <Card className="border border-danger/30">
          <CardBody className="flex flex-row items-center gap-3 text-danger">
            <AlertTriangle size={18} />
            <span className="text-sm">{error}</span>
          </CardBody>
        </Card>
      )}

      {/* Results */}
      {circle && !loading && (
        <>
          {/* Recipient profile card */}
          <Card>
            <CardHeader className="pb-2">
              <span className="text-sm font-semibold text-default-600 uppercase tracking-wide">
                Recipient
              </span>
            </CardHeader>
            <CardBody className="pt-0">
              <div className="flex flex-wrap items-center gap-4">
                <div>
                  <p className="text-xl font-bold">{circle.recipient.name}</p>
                  <p className="text-sm text-default-500">
                    Member since {fmtDate(circle.recipient.member_since)}
                  </p>
                </div>
                <Chip
                  color={tierColor(circle.recipient.trust_tier)}
                  variant="flat"
                  size="sm"
                >
                  {tierLabel(circle.recipient.trust_tier)}
                </Chip>
                {circle.safeguarding_flags > 0 && (
                  <Chip
                    color="danger"
                    variant="solid"
                    size="sm"
                    startContent={<ShieldAlert size={12} />}
                  >
                    {circle.safeguarding_flags} safeguarding flag
                    {circle.safeguarding_flags !== 1 ? 's' : ''}
                  </Chip>
                )}
              </div>
            </CardBody>
          </Card>

          {/* Summary stat row */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <StatCard
              label="Total Hours Received"
              value={circle.total_hours_received}
              icon={Clock}
              color="primary"
            />
            <StatCard
              label="Active Supporters"
              value={activeCount}
              icon={Heart}
              color="success"
            />
            <StatCard
              label="Open Help Requests"
              value={circle.open_help_requests}
              icon={AlertTriangle}
              color={circle.open_help_requests > 0 ? 'warning' : 'default'}
            />
          </div>

          <Divider />

          {/* Support Relationships table */}
          <Card>
            <CardHeader>
              <span className="font-semibold text-sm">Support Relationships</span>
            </CardHeader>
            <CardBody className="p-0">
              {circle.support_relationships.length === 0 ? (
                <div className="flex flex-col items-center gap-2 py-10 text-default-400">
                  <Users2 size={36} className="opacity-30" />
                  <p className="text-sm">No active support relationships</p>
                </div>
              ) : (
                <Table
                  aria-label="Support relationships"
                  removeWrapper
                  classNames={{
                    th: 'bg-[var(--color-surface-alt)] text-xs font-semibold uppercase tracking-wide',
                  }}
                >
                  <TableHeader>
                    <TableColumn>Supporter</TableColumn>
                    <TableColumn>Trust Tier</TableColumn>
                    <TableColumn>Type</TableColumn>
                    <TableColumn>Hours Logged</TableColumn>
                    <TableColumn>Last Activity</TableColumn>
                    <TableColumn>Status</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {circle.support_relationships.map((rel) => (
                      <TableRow key={rel.id}>
                        <TableCell>
                          <div className="font-medium text-sm">{rel.supporter.name}</div>
                          <div className="text-xs text-default-400">ID {rel.supporter.id}</div>
                        </TableCell>
                        <TableCell>
                          <Chip
                            color={tierColor(rel.supporter.trust_tier)}
                            variant="flat"
                            size="sm"
                          >
                            {tierLabel(rel.supporter.trust_tier)}
                          </Chip>
                        </TableCell>
                        <TableCell className="text-sm capitalize">{rel.type}</TableCell>
                        <TableCell className="text-sm font-mono">
                          {rel.hours_logged.toLocaleString()}
                        </TableCell>
                        <TableCell className="text-sm text-default-500 whitespace-nowrap">
                          {fmtDate(rel.last_activity_at)}
                        </TableCell>
                        <TableCell>
                          <Chip
                            color={
                              rel.status === 'active'
                                ? 'success'
                                : rel.status === 'paused'
                                  ? 'warning'
                                  : 'default'
                            }
                            variant="flat"
                            size="sm"
                          >
                            {rel.status}
                          </Chip>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardBody>
          </Card>
        </>
      )}
    </div>
  );
}
