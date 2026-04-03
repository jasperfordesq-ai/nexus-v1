// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Request Create
 * Creation form for new admin-initiated GDPR requests.
 * Route: /admin/enterprise/gdpr/requests/create
 */

import { useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Card, CardBody, Input, Textarea, Select, SelectItem, Button, Chip, Spinner,
} from '@heroui/react';
import {
  ArrowLeft, Save, Eye, Trash2, Download, Edit, Lock, AlertTriangle, Search,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminEnterprise, adminUsers } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { AdminUser } from '../../api/types';

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

  // User search autocomplete state
  const [userSearch, setUserSearch] = useState('');
  const [userResults, setUserResults] = useState<AdminUser[]>([]);
  const [searchLoading, setSearchLoading] = useState(false);
  const [selectedUser, setSelectedUser] = useState<AdminUser | null>(null);
  const [showDropdown, setShowDropdown] = useState(false);
  const [searchDone, setSearchDone] = useState(false);
  const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  const debouncedSearch = (query: string) => {
    if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    if (query.length < 2) {
      setUserResults([]);
      setShowDropdown(false);
      setSearchDone(false);
      return;
    }
    setSearchDone(false);
    searchTimeoutRef.current = setTimeout(async () => {
      setSearchLoading(true);
      try {
        const res = await adminUsers.list({ search: query, page: 1, limit: 10 });
        if (res.success && res.data) {
          const data = res.data as unknown as { data?: AdminUser[] } | AdminUser[];
          const users = Array.isArray(data) ? data : (data as { data?: AdminUser[] }).data || [];
          setUserResults(users);
          setShowDropdown(users.length > 0);
          setSearchDone(true);
        }
      } catch {
        setUserResults([]);
        setSearchDone(true);
      } finally {
        setSearchLoading(false);
      }
    }, 300);
  };

  const handleSelectUser = (user: AdminUser) => {
    setSelectedUser(user);
    setUserId(String(user.id));
    setUserSearch('');
    setUserResults([]);
    setShowDropdown(false);
  };

  const handleDeselectUser = () => {
    setSelectedUser(null);
    setUserId('');
    setUserSearch('');
  };

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
          {/* User Search */}
          <div>
            {selectedUser ? (
              <div className="space-y-2">
                <p className="text-sm font-medium text-default-700">
                  User <span className="text-danger">*</span>
                </p>
                <Chip
                  onClose={handleDeselectUser}
                  variant="flat"
                  color="primary"
                  size="lg"
                >
                  {selectedUser.name} ({selectedUser.email}) — ID #{selectedUser.id}
                </Chip>
              </div>
            ) : (
              <div className="relative">
                <Input
                  label="Search User"
                  placeholder="Type name or email to search..."
                  value={userSearch}
                  onValueChange={(val) => {
                    setUserSearch(val);
                    debouncedSearch(val);
                  }}
                  onFocus={() => {
                    if (userResults.length > 0) setShowDropdown(true);
                  }}
                  onBlur={() => {
                    // Delay to allow click on dropdown items
                    setTimeout(() => setShowDropdown(false), 200);
                  }}
                  variant="bordered"
                  isRequired
                  startContent={<Search size={16} />}
                  endContent={searchLoading ? <Spinner size="sm" /> : undefined}
                />
                {showDropdown && userResults.length > 0 && (
                  <div className="absolute z-50 w-full mt-1 bg-content1 border border-divider rounded-xl shadow-lg max-h-60 overflow-y-auto">
                    {userResults.map((user) => (
                      <button
                        key={user.id}
                        type="button"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => handleSelectUser(user)}
                        className="w-full text-left px-4 py-2.5 hover:bg-default-100 transition-colors first:rounded-t-xl last:rounded-b-xl"
                      >
                        <span className="font-medium text-sm">{user.name}</span>
                        <span className="text-default-500 text-sm"> ({user.email})</span>
                        <span className="text-default-400 text-xs ml-1">— ID #{user.id}</span>
                      </button>
                    ))}
                  </div>
                )}
                {searchDone && !searchLoading && userResults.length === 0 && userSearch.length >= 2 && (
                  <p className="text-xs text-default-400 mt-1">No users found for "{userSearch}"</p>
                )}
              </div>
            )}
          </div>

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
