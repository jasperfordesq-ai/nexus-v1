// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Request Create
 * Creation form for new admin-initiated GDPR requests.
 * Route: /admin/enterprise/gdpr/requests/create
 */

import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Card, CardBody, Input, Textarea, Select, SelectItem, Button,
} from '@heroui/react';
import {
  ArrowLeft, Save, Eye, Trash2, Download, Edit, Lock, AlertTriangle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminEnterprise } from '../../api/adminApi';
import { PageHeader } from '../../components';

import { useTranslation } from 'react-i18next';

interface RequestTypeOption {
  key: string;
  label: string;
  description: string;
  icon: LucideIcon;
}

const REQUEST_TYPES: RequestTypeOption[] = [
  { key: 'access', label: 'Access', description: 'Data access request', icon: Eye },
  { key: 'erasure', label: 'Erasure', description: 'Right to be forgotten', icon: Trash2 },
  { key: 'portability', label: 'Portability', description: 'Data portability', icon: Download },
  { key: 'rectification', label: 'Rectification', description: 'Data correction', icon: Edit },
  { key: 'restriction', label: 'Restriction', description: 'Restrict processing', icon: Lock },
  { key: 'objection', label: 'Objection', description: 'Object to processing', icon: AlertTriangle },
];

const PRIORITY_KEYS = ['low', 'normal', 'high', 'urgent'] as const;

export function GdprRequestCreate() {
  useTranslation('admin');
  usePageTitle('Create GDPR Request');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [userId, setUserId] = useState('');
  const [selectedType, setSelectedType] = useState('');
  const [priority, setPriority] = useState('normal');
  const [notes, setNotes] = useState('');
  const [saving, setSaving] = useState(false);

  const handleSubmit = async () => {
    const parsedUserId = parseInt(userId, 10);
    if (!parsedUserId || isNaN(parsedUserId)) {
      toast.error('Please enter a valid User ID');
      return;
    }
    if (!selectedType) {
      toast.error('Please select a request type');
      return;
    }

    setSaving(true);
    try {
      const res = await adminEnterprise.createGdprRequest({
        user_id: parsedUserId,
        type: selectedType,
        priority,
        notes: notes.trim() || undefined,
      });
      if (res.success) {
        toast.success('GDPR request created successfully');
        navigate(tenantPath('/admin/enterprise/gdpr/requests'));
      } else {
        const error = (res as { error?: string }).error || 'Failed to create request';
        toast.error(error);
      }
    } catch (err) {
      toast.error('Failed to create GDPR request');
      console.error('GDPR request creation error:', err);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <PageHeader
        title="Create GDPR Request"
        description="Create a new admin-initiated GDPR data request"
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/enterprise/gdpr/requests'))}
            size="sm"
          >
            Back to Requests
          </Button>
        }
      />

      <Card shadow="sm">
        <CardBody className="p-6 space-y-6">
          {/* User ID */}
          <Input
            label="User ID"
            placeholder="Enter the user ID"
            type="number"
            value={userId}
            onValueChange={setUserId}
            variant="bordered"
            isRequired
          />

          {/* Request Type - Card Selector */}
          <div>
            <p className="text-sm font-medium text-default-700 mb-3">
              Request Type <span className="text-danger">*</span>
            </p>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {REQUEST_TYPES.map((type) => {
                const isSelected = selectedType === type.key;
                const Icon = type.icon;
                return (
                  <button
                    key={type.key}
                    type="button"
                    onClick={() => setSelectedType(type.key)}
                    className={`
                      flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all
                      cursor-pointer text-center
                      ${isSelected
                        ? 'border-primary bg-primary-50 shadow-sm'
                        : 'border-default-200 bg-default-50 hover:border-default-300'
                      }
                    `}
                  >
                    <div className={`
                      flex h-10 w-10 items-center justify-center rounded-lg
                      ${isSelected ? 'bg-primary/20 text-primary' : 'bg-default-200 text-default-600'}
                    `}>
                      <Icon size={20} />
                    </div>
                    <div>
                      <p className={`font-semibold text-sm ${isSelected ? 'text-primary' : 'text-foreground'}`}>
                        {type.label}
                      </p>
                      <p className="text-xs text-default-500 mt-0.5">{type.description}</p>
                    </div>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Priority */}
          <Select
            label="Priority"
            selectedKeys={[priority]}
            onSelectionChange={(keys) => {
              const val = Array.from(keys)[0] as string;
              if (val) setPriority(val);
            }}
            variant="bordered"
            className="max-w-xs"
          >
            {PRIORITY_KEYS.map((key) => (
              <SelectItem key={key} className="capitalize">
                {key.charAt(0).toUpperCase() + key.slice(1)}
              </SelectItem>
            ))}
          </Select>

          {/* Notes */}
          <Textarea
            label="Notes"
            placeholder="Optional notes about this request..."
            value={notes}
            onValueChange={setNotes}
            variant="bordered"
            minRows={3}
          />

          {/* Actions */}
          <div className="flex justify-end gap-3 pt-2">
            <Button
              variant="flat"
              onPress={() => navigate(tenantPath('/admin/enterprise/gdpr/requests'))}
            >
              Cancel
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSubmit}
              isLoading={saving}
            >
              Create Request
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default GdprRequestCreate;
