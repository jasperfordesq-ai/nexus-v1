// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Credit Agreements (FD1)
 * Admin page for managing inter-tenant credit exchange agreements.
 * Create, approve, suspend, terminate agreements. View balances.
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Spinner,
  Chip,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Select,
  SelectItem,
  useDisclosure,
} from '@heroui/react';
import {
  Handshake,
  Plus,
  RefreshCw,
  CheckCircle,
  XCircle,
  Pause,
  Play,
  ArrowRightLeft,
  DollarSign,
  TrendingUp,
  AlertTriangle,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatRelativeTime } from '@/lib/helpers';
import { PageHeader } from '../../components';
import { StatCard } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface CreditAgreement {
  id: number;
  partner_tenant: {
    id: number;
    name: string;
    slug: string;
  };
  exchange_rate: number;
  monthly_limit: number;
  current_balance: number;
  status: 'pending' | 'active' | 'suspended' | 'terminated';
  credits_sent: number;
  credits_received: number;
  created_at: string;
  updated_at?: string;
  approved_at?: string;
}

interface PartnerTenant {
  id: number;
  name: string;
  slug: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const STATUS_COLORS: Record<string, 'default' | 'primary' | 'success' | 'warning' | 'danger'> = {
  pending: 'warning',
  active: 'success',
  suspended: 'danger',
  terminated: 'default',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function CreditAgreements() {
  usePageTitle('Admin - Credit Agreements');
  const toast = useToast();
  const createModal = useDisclosure();

  const [agreements, setAgreements] = useState<CreditAgreement[]>([]);
  const [partners, setPartners] = useState<PartnerTenant[]>([]);
  const [loading, setLoading] = useState(true);

  // Create form
  const [selectedPartner, setSelectedPartner] = useState('');
  const [exchangeRate, setExchangeRate] = useState('1.0');
  const [monthlyLimit, setMonthlyLimit] = useState('100');
  const [creating, setCreating] = useState(false);

  // ─── Load data ───
  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [agreementsRes, partnersRes] = await Promise.all([
        api.get('/v2/admin/federation/credit-agreements'),
        api.get('/v2/admin/federation/partners'),
      ]);

      if (agreementsRes.success) {
        const payload = agreementsRes.data;
        setAgreements(
          Array.isArray(payload) ? payload : (payload as { agreements?: CreditAgreement[] })?.agreements ?? []
        );
      }

      if (partnersRes.success) {
        const payload = partnersRes.data;
        setPartners(
          Array.isArray(payload) ? payload : (payload as { partners?: PartnerTenant[] })?.partners ?? []
        );
      }
    } catch (err) {
      logError('CreditAgreements.load', err);
      toast.error('Failed to load credit agreements');
    }
    setLoading(false);
  }, [toast]);

  useEffect(() => { loadData(); }, [loadData]);

  // ─── Create agreement ───
  const handleCreate = useCallback(async () => {
    if (!selectedPartner) return;
    setCreating(true);
    try {
      const res = await api.post('/v2/admin/federation/credit-agreements', {
        partner_tenant_id: parseInt(selectedPartner),
        exchange_rate: parseFloat(exchangeRate) || 1.0,
        monthly_limit: parseInt(monthlyLimit) || 100,
      });
      if (res.success) {
        toast.success('Credit agreement created');
        setSelectedPartner('');
        setExchangeRate('1.0');
        setMonthlyLimit('100');
        createModal.onClose();
        loadData();
      }
    } catch (err) {
      logError('CreditAgreements.create', err);
      toast.error('Failed to create agreement');
    }
    setCreating(false);
  }, [selectedPartner, exchangeRate, monthlyLimit, toast, createModal, loadData]);

  // ─── Status change ───
  const handleStatusChange = useCallback(async (agreementId: number, action: 'approve' | 'suspend' | 'terminate' | 'reactivate') => {
    try {
      const res = await api.post(`/v2/admin/federation/credit-agreements/${agreementId}/${action}`);
      if (res.success) {
        toast.success(`Agreement ${action}d`);
        loadData();
      }
    } catch (err) {
      logError('CreditAgreements.statusChange', err);
      toast.error(`Failed to ${action} agreement`);
    }
  }, [toast, loadData]);

  // ─── Stats ───
  const activeCount = agreements.filter((a) => a.status === 'active').length;
  const pendingCount = agreements.filter((a) => a.status === 'pending').length;
  const totalSent = agreements.reduce((sum, a) => sum + (a.credits_sent || 0), 0);
  const totalReceived = agreements.reduce((sum, a) => sum + (a.credits_received || 0), 0);

  // ─── Render ───
  if (loading) {
    return (
      <div>
        <PageHeader title="Credit Agreements" description="Manage inter-community credit exchange agreements" />
        <div className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Credit Agreements"
        description="Manage inter-community credit exchange agreements"
        actions={
          <div className="flex items-center gap-2">
            <Button variant="flat" size="sm" startContent={<RefreshCw size={16} />} onPress={() => loadData()}>
              Refresh
            </Button>
            <Button color="primary" size="sm" startContent={<Plus size={16} />} onPress={createModal.onOpen}>
              New Agreement
            </Button>
          </div>
        }
      />

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard label="Active Agreements" value={activeCount} icon={Handshake} color="success" />
        <StatCard label="Pending Approval" value={pendingCount} icon={AlertTriangle} color="warning" />
        <StatCard label="Credits Sent" value={totalSent} icon={ArrowRightLeft} color="primary" />
        <StatCard label="Credits Received" value={totalReceived} icon={TrendingUp} color="secondary" />
      </div>

      {/* Agreements table */}
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold">All Agreements</h3>
        </CardHeader>
        <CardBody>
          <Table aria-label="Credit agreements" removeWrapper>
            <TableHeader>
              <TableColumn>PARTNER</TableColumn>
              <TableColumn>EXCHANGE RATE</TableColumn>
              <TableColumn>MONTHLY LIMIT</TableColumn>
              <TableColumn>BALANCE</TableColumn>
              <TableColumn>SENT / RECEIVED</TableColumn>
              <TableColumn>STATUS</TableColumn>
              <TableColumn>CREATED</TableColumn>
              <TableColumn>ACTIONS</TableColumn>
            </TableHeader>
            <TableBody emptyContent="No credit agreements found. Create one to start exchanging credits with partner communities.">
              {agreements.map((agreement) => (
                <TableRow key={agreement.id}>
                  <TableCell>
                    <div>
                      <p className="font-medium text-sm">{agreement.partner_tenant.name}</p>
                      <p className="text-xs text-default-400">{agreement.partner_tenant.slug}</p>
                    </div>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm font-mono">{agreement.exchange_rate}:1</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm">{agreement.monthly_limit} credits</span>
                  </TableCell>
                  <TableCell>
                    <span className={`text-sm font-medium ${agreement.current_balance >= 0 ? 'text-success' : 'text-danger'}`}>
                      {agreement.current_balance >= 0 ? '+' : ''}{agreement.current_balance}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm text-default-500">
                      {agreement.credits_sent} / {agreement.credits_received}
                    </span>
                  </TableCell>
                  <TableCell>
                    <Chip size="sm" color={STATUS_COLORS[agreement.status]} variant="flat">
                      {agreement.status}
                    </Chip>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm text-default-400">{formatRelativeTime(agreement.created_at)}</span>
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-1">
                      {agreement.status === 'pending' && (
                        <Button
                          size="sm"
                          variant="flat"
                          color="success"
                          isIconOnly
                          aria-label="Approve"
                          onPress={() => handleStatusChange(agreement.id, 'approve')}
                        >
                          <CheckCircle size={14} />
                        </Button>
                      )}
                      {agreement.status === 'active' && (
                        <Button
                          size="sm"
                          variant="flat"
                          color="warning"
                          isIconOnly
                          aria-label="Suspend"
                          onPress={() => handleStatusChange(agreement.id, 'suspend')}
                        >
                          <Pause size={14} />
                        </Button>
                      )}
                      {agreement.status === 'suspended' && (
                        <Button
                          size="sm"
                          variant="flat"
                          color="success"
                          isIconOnly
                          aria-label="Reactivate"
                          onPress={() => handleStatusChange(agreement.id, 'reactivate')}
                        >
                          <Play size={14} />
                        </Button>
                      )}
                      {agreement.status !== 'terminated' && (
                        <Button
                          size="sm"
                          variant="flat"
                          color="danger"
                          isIconOnly
                          aria-label="Terminate"
                          onPress={() => handleStatusChange(agreement.id, 'terminate')}
                        >
                          <XCircle size={14} />
                        </Button>
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>

      {/* Create Agreement Modal */}
      <Modal isOpen={createModal.isOpen} onOpenChange={createModal.onOpenChange} size="lg">
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex items-center gap-2">
                <Handshake size={20} />
                New Credit Agreement
              </ModalHeader>
              <ModalBody className="gap-4">
                <Select
                  label="Partner Community"
                  placeholder="Select a partner"
                  selectedKeys={selectedPartner ? [selectedPartner] : []}
                  onSelectionChange={(keys) => {
                    const selected = Array.from(keys)[0];
                    setSelectedPartner(selected ? String(selected) : '');
                  }}
                >
                  {partners.map((p) => (
                    <SelectItem key={String(p.id)}>{p.name}</SelectItem>
                  ))}
                </Select>

                <Input
                  type="number"
                  label="Exchange Rate"
                  description="How many of your credits equal 1 partner credit (e.g., 1.0 = 1:1)"
                  value={exchangeRate}
                  onChange={(e) => setExchangeRate(e.target.value)}
                  startContent={<ArrowRightLeft size={14} />}
                  step="0.1"
                  min="0.1"
                />

                <Input
                  type="number"
                  label="Monthly Limit"
                  description="Maximum credits that can be exchanged per month"
                  value={monthlyLimit}
                  onChange={(e) => setMonthlyLimit(e.target.value)}
                  startContent={<DollarSign size={14} />}
                  min="1"
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>Cancel</Button>
                <Button
                  color="primary"
                  isLoading={creating}
                  isDisabled={!selectedPartner}
                  onPress={handleCreate}
                >
                  Create Agreement
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}

export default CreditAgreements;
