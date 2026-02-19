// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Exchange Detail
 * Full detail view for a single exchange request.
 * Parity: PHP BrokerControlsController::showExchange()
 */

import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Card, CardBody, CardHeader, Button, Chip, Divider, Spinner } from '@heroui/react';
import { ArrowLeft, User, Shield, Clock } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminBroker } from '../../api/adminApi';
import type { ExchangeDetail as ExchangeDetailType } from '../../api/types';
import { PageHeader } from '../../components/PageHeader';
import { useTenant } from '@/contexts';

const STATUS_COLORS: Record<string, 'warning' | 'success' | 'danger' | 'default' | 'primary'> = {
  pending_broker: 'warning',
  accepted: 'success',
  cancelled: 'danger',
  disputed: 'danger',
  completed: 'success',
  pending: 'warning',
};

export default function ExchangeDetail() {
  usePageTitle('Admin - Exchange Detail');
  const { id } = useParams<{ id: string }>();
  const { tenantPath } = useTenant();
  const [data, setData] = useState<ExchangeDetailType | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id) return;
    loadExchange(parseInt(id));
  }, [id]);

  async function loadExchange(exchangeId: number) {
    setLoading(true);
    try {
      const res = await adminBroker.showExchange(exchangeId);
      if (res.success && res.data) {
        setData(res.data);
      } else {
        setError('Exchange not found');
      }
    } catch {
      setError('Failed to load exchange');
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
        <p className="text-danger">{error || 'Exchange not found'}</p>
        <Button
          as={Link}
          to={tenantPath('/admin/broker-controls/exchanges')}
          variant="flat"
          className="mt-4"
          startContent={<ArrowLeft className="w-4 h-4" />}
        >
          Back to Exchanges
        </Button>
      </div>
    );
  }

  const { exchange, history, risk_tag } = data;
  const statusColor = STATUS_COLORS[exchange.status] ?? 'default';

  return (
    <div className="space-y-6">
      <PageHeader
        title={`Exchange #${exchange.id}`}
        description={exchange.listing_title ?? 'Exchange Request'}
        actions={
          <Button
            as={Link}
            to={tenantPath('/admin/broker-controls/exchanges')}
            variant="flat"
            startContent={<ArrowLeft className="w-4 h-4" />}
            size="sm"
          >
            Back
          </Button>
        }
      />

      {/* Status + basic info */}
      <Card shadow="sm">
        <CardBody className="flex flex-row items-center justify-between">
          <div className="space-y-1">
            <p className="text-sm text-default-500">Status</p>
            <Chip color={statusColor} variant="flat" size="sm" className="capitalize">
              {exchange.status.replace(/_/g, ' ')}
            </Chip>
          </div>
          {exchange.final_hours !== undefined && exchange.final_hours !== null && (
            <div className="space-y-1 text-center">
              <p className="text-sm text-default-500">Hours</p>
              <p className="text-sm font-semibold">{exchange.final_hours}h</p>
            </div>
          )}
          <div className="space-y-1 text-right">
            <p className="text-sm text-default-500">Created</p>
            <p className="text-sm">{new Date(exchange.created_at).toLocaleString()}</p>
          </div>
        </CardBody>
      </Card>

      {/* Parties */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2">
            <User className="w-4 h-4" />
            <span className="font-semibold">Requester</span>
          </CardHeader>
          <Divider />
          <CardBody>
            <p className="font-medium">{exchange.requester_name}</p>
            {exchange.requester_email && (
              <p className="text-sm text-default-500">{exchange.requester_email}</p>
            )}
          </CardBody>
        </Card>
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2">
            <User className="w-4 h-4" />
            <span className="font-semibold">Provider</span>
          </CardHeader>
          <Divider />
          <CardBody>
            <p className="font-medium">{exchange.provider_name}</p>
            {exchange.provider_email && (
              <p className="text-sm text-default-500">{exchange.provider_email}</p>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Risk Tag */}
      {risk_tag && (
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2">
            <Shield className="w-4 h-4 text-warning" />
            <span className="font-semibold">Risk Tag</span>
          </CardHeader>
          <Divider />
          <CardBody>
            <div className="flex items-center gap-3">
              <Chip
                color={risk_tag.risk_level === 'critical' || risk_tag.risk_level === 'high' ? 'danger' : 'warning'}
                variant="flat"
                size="sm"
                className="capitalize"
              >
                {risk_tag.risk_level}
              </Chip>
              <span className="text-sm capitalize">{risk_tag.risk_category}</span>
            </div>
            {risk_tag.risk_notes && (
              <p className="text-sm text-default-500 mt-2">{risk_tag.risk_notes}</p>
            )}
            <div className="flex gap-3 mt-3">
              {risk_tag.requires_approval && (
                <Chip size="sm" variant="dot" color="warning">Approval Required</Chip>
              )}
              {risk_tag.insurance_required && (
                <Chip size="sm" variant="dot" color="warning">Insurance Required</Chip>
              )}
              {risk_tag.dbs_required && (
                <Chip size="sm" variant="dot" color="warning">DBS Required</Chip>
              )}
            </div>
          </CardBody>
        </Card>
      )}

      {/* Broker Notes */}
      {exchange.broker_notes && (
        <Card shadow="sm">
          <CardHeader><span className="font-semibold">Broker Notes</span></CardHeader>
          <Divider />
          <CardBody>
            <p className="text-sm">{exchange.broker_notes}</p>
          </CardBody>
        </Card>
      )}

      {/* Broker Conditions */}
      {exchange.broker_conditions && (
        <Card shadow="sm">
          <CardHeader><span className="font-semibold">Broker Conditions</span></CardHeader>
          <Divider />
          <CardBody>
            <p className="text-sm">{exchange.broker_conditions}</p>
          </CardBody>
        </Card>
      )}

      {/* History Timeline */}
      <Card shadow="sm">
        <CardHeader className="flex items-center gap-2">
          <Clock className="w-4 h-4" />
          <span className="font-semibold">History</span>
        </CardHeader>
        <Divider />
        <CardBody>
          {history.length === 0 ? (
            <p className="text-sm text-default-500">No history recorded</p>
          ) : (
            <div className="space-y-3">
              {history.map((entry) => (
                <div key={entry.id} className="flex gap-3 items-start">
                  <div className="w-2 h-2 rounded-full bg-primary mt-2 flex-shrink-0" />
                  <div>
                    <p className="text-sm font-medium">{entry.action}</p>
                    {entry.actor_name && (
                      <p className="text-xs text-default-500">by {entry.actor_name}</p>
                    )}
                    {entry.notes && (
                      <p className="text-xs text-default-400 mt-1">{entry.notes}</p>
                    )}
                    <p className="text-xs text-default-300">{new Date(entry.created_at).toLocaleString()}</p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
