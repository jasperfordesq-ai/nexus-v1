// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Archive Detail
 * Read-only compliance record for a reviewed broker message copy.
 * Shows decision info, the target message, and a frozen conversation snapshot.
 * No action buttons — this is a pure read-only view.
 */

import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Chip,
  Divider,
  Spinner,
  ScrollShadow,
} from '@heroui/react';
import { ArrowLeft, Lock, Flag, CheckCircle, User, Mail, MessageSquare } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import { adminBroker } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { BrokerArchiveDetail as BrokerArchiveDetailType } from '../../api/types';

export function ArchiveDetail() {
  usePageTitle('Admin - Archive Detail');
  const { id } = useParams<{ id: string }>();
  const { tenantPath } = useTenant();
  const [data, setData] = useState<BrokerArchiveDetailType | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;
    loadArchive(Number(id));
  }, [id]);

  async function loadArchive(archiveId: number) {
    setLoading(true);
    try {
      const res = await adminBroker.showArchive(archiveId);
      if (res.success && res.data) {
        setData(res.data);
      } else {
        setError('Archive record not found');
      }
    } catch {
      setError('Failed to load archive record');
    } finally {
      setLoading(false);
    }
  }

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[300px]">
        <Spinner size="lg" />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="text-center py-12">
        <p className="text-danger">{error || 'Archive record not found'}</p>
        <Button
          as={Link}
          to={tenantPath('/admin/broker-controls/archives')}
          variant="flat"
          className="mt-4"
          startContent={<ArrowLeft size={16} />}
        >
          Back to Archives
        </Button>
      </div>
    );
  }

  const isApproved = data.decision === 'approved';

  return (
    <div className="space-y-6">
      <PageHeader
        title="Archive Detail"
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/broker-controls/archives')}
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            size="sm"
          >
            Back
          </Button>
        }
      />

      {/* Read-only compliance badge */}
      <Chip
        color="secondary"
        variant="flat"
        size="lg"
        startContent={<Lock size={14} />}
      >
        Read-only compliance record
      </Chip>

      {/* Decision Card */}
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2">
          {isApproved ? (
            <CheckCircle size={18} className="text-success" />
          ) : (
            <Flag size={18} className="text-danger" />
          )}
          <span className="font-semibold">Decision</span>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-3">
          <div className="flex flex-wrap items-center gap-4">
            <div>
              <p className="text-xs text-default-400">Decision</p>
              <Chip
                size="sm"
                variant="flat"
                color={isApproved ? 'success' : 'danger'}
                className="capitalize mt-1"
              >
                {data.decision}
              </Chip>
            </div>
            <div>
              <p className="text-xs text-default-400">Decided By</p>
              <p className="text-sm font-medium mt-1">{data.decided_by_name}</p>
            </div>
            <div>
              <p className="text-xs text-default-400">Date</p>
              <p className="text-sm mt-1">
                {new Date(data.decided_at).toLocaleString()}
              </p>
            </div>
          </div>

          {data.decision_notes && (
            <div>
              <p className="text-xs text-default-400">Decision Notes</p>
              <p className="text-sm mt-1">{data.decision_notes}</p>
            </div>
          )}

          {data.flag_reason && (
            <div className="rounded-lg bg-danger-50 p-3">
              <p className="text-xs text-danger font-medium">Flag Reason</p>
              <p className="text-sm mt-1">{data.flag_reason}</p>
              {data.flag_severity && (
                <Chip
                  size="sm"
                  variant="flat"
                  color="danger"
                  className="capitalize mt-2"
                >
                  Severity: {data.flag_severity}
                </Chip>
              )}
            </div>
          )}
        </CardBody>
      </Card>

      {/* Target Message Card */}
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2">
          <Mail size={18} />
          <span className="font-semibold">Target Message</span>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-3">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="flex items-center gap-2">
              <User size={14} className="text-default-400" />
              <div>
                <p className="text-xs text-default-400">Sender</p>
                <p className="text-sm font-medium">{data.sender_name}</p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <User size={14} className="text-default-400" />
              <div>
                <p className="text-xs text-default-400">Receiver</p>
                <p className="text-sm font-medium">{data.receiver_name}</p>
              </div>
            </div>
          </div>

          <Divider />

          <div>
            <p className="text-xs text-default-400">Message Body</p>
            <p className="text-sm mt-1 whitespace-pre-wrap">{data.target_message_body}</p>
          </div>

          <div className="flex flex-wrap gap-4">
            <div>
              <p className="text-xs text-default-400">Copy Reason</p>
              <Chip size="sm" variant="flat" color="default" className="capitalize mt-1">
                {data.copy_reason.replace(/_/g, ' ')}
              </Chip>
            </div>
            <div>
              <p className="text-xs text-default-400">Sent At</p>
              <p className="text-sm mt-1">
                {new Date(data.target_message_sent_at).toLocaleString()}
              </p>
            </div>
            {data.listing_title && (
              <div>
                <p className="text-xs text-default-400">Listing</p>
                <p className="text-sm mt-1">{data.listing_title}</p>
              </div>
            )}
          </div>
        </CardBody>
      </Card>

      {/* Conversation Snapshot Card */}
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2">
          <MessageSquare size={18} />
          <span className="font-semibold">Conversation Snapshot</span>
          <Chip size="sm" variant="flat" color="default" className="ml-auto">
            {data.conversation_snapshot.length} messages
          </Chip>
        </CardHeader>
        <Divider />
        <CardBody>
          {data.conversation_snapshot.length === 0 ? (
            <p className="text-sm text-default-500">No conversation snapshot available</p>
          ) : (
            <ScrollShadow className="max-h-[500px]">
              <div className="space-y-0">
                {data.conversation_snapshot.map((msg, index) => (
                  <div key={msg.id}>
                    {index > 0 && <Divider className="my-3" />}
                    <div className="py-1">
                      <div className="flex items-center justify-between mb-1">
                        <span className="text-sm font-semibold text-foreground">
                          {msg.sender_name}
                        </span>
                        <span className="text-xs text-default-400">
                          {new Date(msg.created_at).toLocaleString()}
                        </span>
                      </div>
                      <p className="text-sm text-default-600 whitespace-pre-wrap">
                        {msg.is_deleted ? (
                          <span className="italic text-default-400">[Message deleted]</span>
                        ) : (
                          msg.body
                        )}
                      </p>
                      {msg.is_edited && (
                        <span className="text-xs text-default-400 italic">(edited)</span>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </ScrollShadow>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default ArchiveDetail;
