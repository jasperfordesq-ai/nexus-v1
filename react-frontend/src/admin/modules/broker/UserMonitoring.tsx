// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * User Monitoring
 * View users currently under messaging monitoring restrictions.
 * Parity: PHP BrokerControlsController::monitoring()
 */

import { useState, useCallback, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  Button,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Switch,
} from '@heroui/react';
import { ArrowLeft, Eye, UserPlus, UserMinus } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminBroker } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '../../components';
import type { MonitoredUser } from '../../api/types';

export function UserMonitoring() {
  usePageTitle('Admin - User Monitoring');
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [items, setItems] = useState<MonitoredUser[]>([]);
  const [loading, setLoading] = useState(true);

  // Add to monitoring modal state
  const [monitoringModalOpen, setMonitoringModalOpen] = useState(false);
  const [monitoringUserIdInput, setMonitoringUserIdInput] = useState('');
  const [monitoringReason, setMonitoringReason] = useState('');
  const [messagingDisabled, setMessagingDisabled] = useState(false);
  const [monitoringLoading, setMonitoringLoading] = useState(false);
  const [removingId, setRemovingId] = useState<number | null>(null);

  const loadItems = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminBroker.getMonitoring();
      if (res.success && Array.isArray(res.data)) {
        setItems(res.data);
      }
    } catch {
      // Silently handle
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadItems();
  }, [loadItems]);

  const handleAddMonitoring = async () => {
    const userId = parseInt(monitoringUserIdInput, 10);
    if (!userId || isNaN(userId)) {
      toast.error('Please enter a valid user ID');
      return;
    }
    if (!monitoringReason.trim()) {
      toast.error('A reason is required');
      return;
    }
    setMonitoringLoading(true);
    try {
      const res = await adminBroker.setMonitoring(userId, {
        under_monitoring: true,
        reason: monitoringReason,
        messaging_disabled: messagingDisabled,
      });
      if (res?.success) {
        toast.success('User added to monitoring');
        setMonitoringModalOpen(false);
        setMonitoringUserIdInput('');
        setMonitoringReason('');
        setMessagingDisabled(false);
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to add user to monitoring');
      }
    } catch {
      toast.error('Failed to add user to monitoring');
    } finally {
      setMonitoringLoading(false);
    }
  };

  const handleRemoveMonitoring = async (userId: number) => {
    if (!window.confirm('Remove this user from monitoring?')) return;
    setRemovingId(userId);
    try {
      const res = await adminBroker.setMonitoring(userId, { under_monitoring: false });
      if (res?.success) {
        toast.success('User removed from monitoring');
        loadItems();
      } else {
        toast.error(res?.error || 'Failed to remove user from monitoring');
      }
    } catch {
      toast.error('Failed to remove user from monitoring');
    } finally {
      setRemovingId(null);
    }
  };

  const columns: Column<MonitoredUser>[] = [
    {
      key: 'user_name',
      label: 'User',
      sortable: true,
      render: (item) => (
        <span className="font-medium text-foreground">{item.user_name}</span>
      ),
    },
    {
      key: 'under_monitoring',
      label: 'Status',
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={item.under_monitoring ? 'warning' : 'default'}
          startContent={<Eye size={12} />}
        >
          {item.under_monitoring ? 'Under Monitoring' : 'Not Monitored'}
        </Chip>
      ),
    },
    {
      key: 'monitoring_reason',
      label: 'Reason',
      render: (item) => (
        <span className="text-sm text-default-600">
          {item.monitoring_reason || '—'}
        </span>
      ),
    },
    {
      key: 'monitoring_started_at',
      label: 'Started',
      sortable: true,
      render: (item) => (
        <span className="text-sm text-default-500">
          {item.monitoring_started_at
            ? new Date(item.monitoring_started_at).toLocaleDateString()
            : '—'
          }
        </span>
      ),
    },
    {
      key: 'actions',
      label: 'Actions',
      render: (item) => (
        <Button
          isIconOnly
          size="sm"
          variant="flat"
          color="danger"
          onPress={() => handleRemoveMonitoring(item.user_id)}
          isLoading={removingId === item.user_id}
          aria-label="Remove from monitoring"
        >
          <UserMinus size={14} />
        </Button>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="User Monitoring"
        description="Users under messaging monitoring restrictions"
        actions={
          <div className="flex gap-2">
            <Button
              color="primary"
              startContent={<UserPlus size={16} />}
              size="sm"
              onPress={() => setMonitoringModalOpen(true)}
            >
              Add to Monitoring
            </Button>
            <Button
              as={Link}
              to={tenantPath('/admin/broker-controls')}
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              size="sm"
            >
              Back
            </Button>
          </div>
        }
      />

      {!loading && items.length === 0 ? (
        <EmptyState
          icon={Eye}
          title="No Monitored Users"
          description="No users are currently under monitoring restrictions."
        />
      ) : (
        <DataTable
          columns={columns}
          data={items}
          isLoading={loading}
          searchable={false}
          onRefresh={loadItems}
        />
      )}

      {/* Add to Monitoring Modal */}
      <Modal
        isOpen={monitoringModalOpen}
        onClose={() => setMonitoringModalOpen(false)}
        size="md"
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <UserPlus size={20} className="text-primary" />
            Add User to Monitoring
          </ModalHeader>
          <ModalBody>
            <Input
              label="User ID"
              placeholder="Enter numeric user ID"
              value={monitoringUserIdInput}
              onValueChange={setMonitoringUserIdInput}
              type="number"
              variant="bordered"
              isRequired
            />
            <Textarea
              label="Reason (required)"
              placeholder="Reason for placing this user under monitoring..."
              value={monitoringReason}
              onValueChange={setMonitoringReason}
              minRows={3}
              variant="bordered"
              isRequired
            />
            <div className="flex items-center justify-between py-1">
              <span className="text-sm text-default-600">Disable messaging</span>
              <Switch
                isSelected={messagingDisabled}
                onValueChange={setMessagingDisabled}
                size="sm"
              />
            </div>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => setMonitoringModalOpen(false)}
              isDisabled={monitoringLoading}
            >
              Cancel
            </Button>
            <Button
              color="primary"
              onPress={handleAddMonitoring}
              isLoading={monitoringLoading}
              startContent={!monitoringLoading && <UserPlus size={14} />}
            >
              Add to Monitoring
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default UserMonitoring;
